<?php

namespace App\Services;

use App\Models\AktivitasBaca;
use App\Models\Antrean;
use App\Models\KoleksiBuku;
use App\Models\Notifikasi;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CirculationService
{
    public function borrowOrQueue(User $user, int $bookId): array
    {
        return DB::transaction(function () use ($user, $bookId) {
            $book = KoleksiBuku::query()->lockForUpdate()->findOrFail($bookId);
            $hasActiveLoan = Peminjaman::query()
                ->where('id_pengguna', $user->id_pengguna)
                ->where('id_buku', $bookId)
                ->where('status_pinjam', 'aktif')
                ->exists();
            $hasWaitingQueue = Antrean::query()
                ->where('id_pengguna', $user->id_pengguna)
                ->where('id_buku', $bookId)
                ->where('status_antrean', 'menunggu')
                ->exists();

            if ($hasActiveLoan || $hasWaitingQueue) {
                throw ValidationException::withMessages([
                    'id_buku' => 'Anda sudah meminjam atau sedang mengantre untuk buku ini.',
                ]);
            }

            $this->promoteWaiting($book);

            $activeCount = Peminjaman::query()
                ->where('id_buku', $bookId)
                ->where('status_pinjam', 'aktif')
                ->count();

            if ($activeCount < $book->kuota_maks && ! $this->hasWaitingQueue($bookId)) {
                $loan = $this->createLoan($user->id_pengguna, $book);

                return ['status' => 'borrowed', 'data' => $loan->load('buku')];
            }

            $waitingCount = Antrean::query()
                ->where('id_buku', $bookId)
                ->where('status_antrean', 'menunggu')
                ->count();

            if ($waitingCount >= 30) {
                throw ValidationException::withMessages([
                    'id_buku' => 'Antrean buku ini sudah penuh.',
                ]);
            }

            $queue = Antrean::query()->create([
                'id_pengguna' => $user->id_pengguna,
                'id_buku' => $bookId,
                'nomor_urut' => ((int) Antrean::query()->where('id_buku', $bookId)->max('nomor_urut')) + 1,
                'waktu_masuk' => now(),
                'status_antrean' => 'menunggu',
            ]);

            $this->notify(
                $user->id_pengguna,
                'antrean_tiba',
                "Anda masuk antrean untuk buku \"{$book->judul}\".",
                $bookId
            );

            return ['status' => 'queued', 'data' => $queue->load('buku')];
        }, attempts: 3);
    }

    public function returnLoan(Peminjaman $loan, string $status = 'selesai'): Peminjaman
    {
        return DB::transaction(function () use ($loan, $status) {
            $book = KoleksiBuku::query()->lockForUpdate()->findOrFail($loan->id_buku);
            $lockedLoan = Peminjaman::query()->lockForUpdate()->findOrFail($loan->id_peminjaman);

            if ($lockedLoan->status_pinjam !== 'aktif') {
                return $lockedLoan;
            }

            $lockedLoan->update(['status_pinjam' => $status]);
            $this->promoteWaiting($book);

            return $lockedLoan->refresh();
        }, attempts: 3);
    }

    public function promoteWaiting(KoleksiBuku $book): void
    {
        $activeCount = Peminjaman::query()
            ->where('id_buku', $book->id_buku)
            ->where('status_pinjam', 'aktif')
            ->count();

        while ($activeCount < $book->kuota_maks) {
            $next = Antrean::query()
                ->where('id_buku', $book->id_buku)
                ->where('status_antrean', 'menunggu')
                ->orderBy('waktu_masuk')
                ->orderBy('id_antrean')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                break;
            }

            $next->update(['status_antrean' => 'diproses']);
            $loan = $this->createLoan($next->id_pengguna, $book);
            $this->notify(
                $next->id_pengguna,
                'antrean_tiba',
                "Giliran Anda tiba! \"{$book->judul}\" otomatis dipinjamkan hingga {$loan->tanggal_jatuh_tempo->format('d M Y')}.",
                $book->id_buku
            );
            $activeCount++;
        }
    }

    public function processAutomaticReturns(): void
    {
        Peminjaman::query()
            ->where('status_pinjam', 'aktif')
            ->with('buku')
            ->chunkById(100, function ($loans) {
                foreach ($loans as $loan) {
                    $readingRequirementMissed = $this->hasMissedReadingWindow($loan);

                    if ($readingRequirementMissed) {
                        $this->returnLoan($loan, 'timeout');

                        continue;
                    }

                    $this->sendReminders($loan);

                    if ($loan->tanggal_jatuh_tempo->isPast()) {
                        $this->returnLoan($loan, 'timeout');
                    }
                }
            }, 'id_peminjaman');
    }

    public function readingWindow(Peminjaman $loan): array
    {
        $loanStart = $loan->tanggal_mulai->copy()->startOfDay();
        $daysSinceStart = max(0, (int) $loanStart->diffInDays(now()->startOfDay()));
        $cycle = min(3, intdiv($daysSinceStart, 3));
        $start = $loanStart->copy()->addDays($cycle * 3);

        return [
            'start' => $start,
            'end' => $start->copy()->addDays(2),
            'target_active' => $daysSinceStart < 12,
        ];
    }

    private function hasReachedReadingTarget(Peminjaman $loan, Carbon $start, Carbon $end): bool
    {
        return (int) AktivitasBaca::query()
            ->where('id_peminjaman', $loan->id_peminjaman)
            ->whereDate('tanggal_baca', '>=', $start->toDateString())
            ->whereDate('tanggal_baca', '<=', $end->toDateString())
            ->sum('durasi_menit') >= 30;
    }

    private function hasMissedReadingWindow(Peminjaman $loan): bool
    {
        $loanStart = $loan->tanggal_mulai->copy()->startOfDay();
        $daysSinceStart = max(0, (int) $loanStart->diffInDays(now()->startOfDay()));
        $completedWindows = intdiv($daysSinceStart, 3);

        for ($cycle = 0; $cycle < $completedWindows; $cycle++) {
            $start = $loanStart->copy()->addDays($cycle * 3);
            if (! $this->hasReachedReadingTarget($loan, $start, $start->copy()->addDays(2))) {
                return true;
            }
        }

        return false;
    }

    private function sendReminders(Peminjaman $loan): void
    {
        $bookTitle = $loan->buku->judul;
        $loanStart = $loan->tanggal_mulai->copy()->startOfDay();
        $daysSinceStart = max(0, (int) $loanStart->diffInDays(now()->startOfDay()));

        if ($daysSinceStart >= 12) {
            if ($daysSinceStart <= 13 && ! $loan->tanggal_jatuh_tempo->isPast()) {
                $this->notifyOnceToday(
                    $loan->id_pengguna,
                    'masa_pinjam_hampir_habis',
                    "Hak akses pinjam \"{$bookTitle}\" akan segera selesai.",
                    $loan->id_buku
                );
            }

            return;
        }

        $window = $this->readingWindow($loan);
        if ($window['target_active'] && ! $this->hasReachedReadingTarget($loan, $window['start'], $window['end'])) {
            $this->notifyOnceToday(
                $loan->id_pengguna,
                'pengingat_durasi',
                "Durasi 30 menit belum terpenuhi untuk \"{$bookTitle}\".",
                $loan->id_buku
            );
        }
    }

    private function hasWaitingQueue(int $bookId): bool
    {
        return Antrean::query()
            ->where('id_buku', $bookId)
            ->where('status_antrean', 'menunggu')
            ->exists();
    }

    private function createLoan(int $userId, KoleksiBuku $book): Peminjaman
    {
        return Peminjaman::query()->create([
            'id_pengguna' => $userId,
            'id_buku' => $book->id_buku,
            'tanggal_mulai' => now(),
            'tanggal_jatuh_tempo' => now()->addWeeks(2),
            'status_pinjam' => 'aktif',
        ]);
    }

    private function notify(int $userId, string $type, string $message, ?int $bookId = null): void
    {
        Notifikasi::query()->create([
            'id_pengguna' => $userId,
            'id_buku' => $bookId,
            'jenis_notifikasi' => $type,
            'isi_pesan' => $message,
            'waktu_kirim' => now(),
        ]);
    }

    private function notifyOnceToday(int $userId, string $type, string $message, ?int $bookId = null): void
    {
        $alreadySent = Notifikasi::query()
            ->where('id_pengguna', $userId)
            ->where('jenis_notifikasi', $type)
            ->whereDate('waktu_kirim', Carbon::today())
            ->where('isi_pesan', $message)
            ->exists();

        if (! $alreadySent) {
            $this->notify($userId, $type, $message, $bookId);
        }
    }
}
