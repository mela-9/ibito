<?php

namespace Database\Seeders;

use App\Models\KoleksiBuku;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private const PREFIXES = [
        'Catatan tentang',
        'Menjelajahi',
        'Mengenal',
        'Belajar dari',
        'Merawat',
        'Membaca',
        'Perjalanan ke',
        'Rahasia',
        'Kisah dari',
        'Jejak di',
    ];

    private const SUBJECTS = [
        'Hutan Tropis',
        'Bintang Utara',
        'Kota Tua',
        'Bahasa Nusantara',
        'Laut Selatan',
        'Ruang Angkasa',
        'Seni Membatik',
        'Sejarah Maritim',
        'Ekonomi Kreatif',
        'Hidup Berkelanjutan',
    ];

    private const CATEGORIES = [
        'Alam',
        'Sains',
        'Sejarah',
        'Budaya',
        'Lingkungan',
        'Teknologi',
        'Seni',
        'Sejarah',
        'Ekonomi',
        'Pengembangan Diri',
    ];

    private const FIRST_NAMES = [
        'Aditya', 'Aisyah', 'Andi', 'Anisa', 'Arif',
        'Ayu', 'Bagas', 'Citra', 'Dimas', 'Dwi',
        'Eka', 'Fajar', 'Farah', 'Galih', 'Intan',
        'Joko', 'Kirana', 'Lestari', 'Nabila', 'Raka',
    ];

    private const LAST_NAMES = ['Putra', 'Pratama', 'Wijaya', 'Lestari', 'Saputra'];

    public function run(): void
    {
        $password = env('DEMO_USERS_PASSWORD') ?: '12345678910';
        $periodStart = now()->startOfDay();
        $books = $this->seedBooks($periodStart);
        $members = $this->seedMembers($password, $periodStart);
        $showcaseMember = $this->seedShowcaseMember($password);
        $this->seedLibrarian($password);
        $this->clearDemoActivity(array_merge($members, [$showcaseMember]));
        $this->seedCompletedLoans($members, $books, $periodStart);
        $this->seedActiveLoans($members, $books, $periodStart);
        $this->seedWaitingQueues($members, $books, $periodStart);
        $this->seedNotifications($members, $books, $periodStart);
        $this->seedShowcaseActivity($showcaseMember, $books, $periodStart);

        $this->command?->info('Seed data selesai: 100 buku, 101 akun anggota, pustakawan, riwayat pinjam/baca, dan antrean demo.');
        $this->command?->warn('Kata sandi akun demo: '.$password);
        $this->command?->line('Akun pustakawan: pustakawan@ibi.local');
        $this->command?->line('Akun demo: anggota001@demo.ibi.local sampai anggota101@demo.ibi.local');
        $this->command?->line('Akun simulasi gabungan: anggota101@demo.ibi.local');
        $this->command?->line('Seeder memperbarui riwayat demo untuk 101 anggota tersebut dan tidak menghapus akun pustakawan lain.');
    }

    private function seedBooks(Carbon $periodStart): array
    {
        $books = [];

        foreach (self::PREFIXES as $prefixIndex => $prefix) {
            foreach (self::SUBJECTS as $subjectIndex => $subject) {
                $index = ($prefixIndex * count(self::SUBJECTS)) + $subjectIndex;
                $title = "{$prefix} {$subject}";
                $book = KoleksiBuku::query()->updateOrCreate(
                    ['judul' => $title],
                    [
                        'penulis' => $this->authorFor($index),
                        'kategori' => self::CATEGORIES[$subjectIndex],
                        'tahun_terbit' => 2000 + (($index * 7) % 26),
                        'kata_kunci' => [$title, $subject, self::CATEGORIES[$subjectIndex]],
                        'ringkasan' => "Bacaan pilihan tentang {$subject} yang disusun untuk menemani pembaca menjelajahi gagasan, pengetahuan, dan pengalaman baru.",
                        'berkas_ebook' => null,
                        'kuota_maks' => $index < 7 ? 3 : 30,
                    ]
                );

                if ($book->wasRecentlyCreated) {
                    DB::table('koleksi_buku')->where('id_buku', $book->id_buku)->update([
                        'created_at' => $periodStart->copy()->subDays($index % 15)->toDateTimeString(),
                        'updated_at' => $periodStart->copy()->subDays($index % 15)->toDateTimeString(),
                    ]);
                }

                $books[] = $book;
            }
        }

        return $books;
    }

    private function seedMembers(string $password, Carbon $periodStart): array
    {
        $rows = [];
        for ($index = 0; $index < 100; $index++) {
            $firstName = self::FIRST_NAMES[$index % count(self::FIRST_NAMES)];
            $lastName = self::LAST_NAMES[intdiv($index, count(self::FIRST_NAMES))];
            $email = sprintf('anggota%03d@demo.ibi.local', $index + 1);
            $registeredAt = $periodStart->copy()->subDays($index % 15)->addHours($index % 8);

            $rows[] = [
                'nama' => "{$firstName} {$lastName}",
                'email' => $email,
                'kata_sandi' => $password,
                'peran' => 'anggota',
                'status_verifikasi' => true,
                'created_at' => $registeredAt->toDateTimeString(),
                'updated_at' => $registeredAt->toDateTimeString(),
            ];
        }

        $hashedPassword = Hash::make($password);
        foreach ($rows as &$row) {
            $row['kata_sandi'] = $hashedPassword;
        }
        unset($row);

        DB::table('pengguna')->upsert(
            $rows,
            ['email'],
            ['nama', 'kata_sandi', 'peran', 'status_verifikasi', 'created_at', 'updated_at']
        );

        $emails = array_column($rows, 'email');

        return User::query()->whereIn('email', $emails)->orderBy('email')->get()->all();
    }

    private function seedLibrarian(string $password): void
    {
        $librarian = User::query()->firstOrNew(['email' => 'pustakawan@ibi.local']);
        $librarian->fill([
            'nama' => 'Pustakawan',
            'kata_sandi' => $password,
            'peran' => 'pustakawan',
            'status_verifikasi' => true,
        ]);
        $librarian->save();
    }

    private function seedShowcaseMember(string $password): User
    {
        $member = User::query()->firstOrNew(['email' => 'anggota101@demo.ibi.local']);
        $member->fill([
            'nama' => 'Anggota Demo Gabungan',
            'kata_sandi' => $password,
            'peran' => 'anggota',
            'status_verifikasi' => true,
        ]);
        $member->save();

        return $member;
    }

    private function seedShowcaseActivity(User $member, array $books, Carbon $periodStart): void
    {
        $completedStart = $periodStart->copy()->subDays(30)->addHours(9);
        $completedLoanId = $this->upsertLoan(
            $member->id_pengguna,
            $books[7]->id_buku,
            $completedStart,
            $completedStart->copy()->addWeeks(2),
            'selesai',
            $completedStart,
            $completedStart->copy()->addDays(7)
        );
        $this->seedReadingActivities($completedLoanId, $completedStart, 4);

        // 1. Catatan tentang Hidup Berkelanjutan: jatuh tempo 2 hari lagi, hari ke-13 pinjaman, bebas aturan target membaca
        $sustainableStart = $periodStart->copy()->subDays(12)->addHours(8);
        $sustainableDue = $periodStart->copy()->addDays(2)->addHours(8);
        $sustainableLoanId = $this->upsertLoan(
            $member->id_pengguna,
            $books[9]->id_buku,
            $sustainableStart,
            $sustainableDue,
            'aktif',
            $sustainableStart,
            $sustainableStart
        );
        $this->seedReadingActivities($sustainableLoanId, $sustainableStart, 4);
        $this->upsertNotification(
            $member->id_pengguna,
            'masa_pinjam_hampir_habis',
            "Hak akses pinjam \"{$books[9]->judul}\" akan segera selesai.",
            $periodStart->copy()->addHours(8),
            false,
            $books[9]->id_buku
        );

        // 2. Catatan tentang Ekonomi Kreatif: jatuh tempo 3 hari lagi, sisa 1 hari periode membaca, durasi 27 menit
        $creativeStart = $periodStart->copy()->subDays(10)->addHours(8);
        $creativeDue = $periodStart->copy()->addDays(3)->addHours(8);
        $creativeLoanId = $this->upsertLoan(
            $member->id_pengguna,
            $books[8]->id_buku,
            $creativeStart,
            $creativeDue,
            'aktif',
            $creativeStart,
            $creativeStart
        );
        $this->seedReadingActivities($creativeLoanId, $creativeStart, 3);
        $activityDate = $periodStart->copy()->subDay();
        DB::table('aktivitas_baca')->updateOrInsert(
            [
                'id_peminjaman' => $creativeLoanId,
                'tanggal_baca' => $activityDate->toDateString(),
            ],
            [
                'durasi_menit' => 27,
                'status_syarat' => false,
                'created_at' => $activityDate->copy()->setTime(19, 0)->toDateTimeString(),
                'updated_at' => $activityDate->copy()->setTime(19, 0)->toDateTimeString(),
            ]
        );
        $this->upsertNotification(
            $member->id_pengguna,
            'pengingat_durasi',
            "Durasi 30 menit belum terpenuhi untuk \"{$books[8]->judul}\".",
            $periodStart->copy()->addHours(9),
            false,
            $books[8]->id_buku
        );

        // 3. Menjelajahi Hutan Tropis: jatuh tempo 4 hari lagi, hari ke-10 selesai 30 menit, misi terpenuhi
        $tropicalStart = $periodStart->copy()->subDays(10)->addHours(8);
        $tropicalDue = $periodStart->copy()->addDays(4)->addHours(8);
        $tropicalLoanId = $this->upsertLoan(
            $member->id_pengguna,
            $books[10]->id_buku,
            $tropicalStart,
            $tropicalDue,
            'aktif',
            $tropicalStart,
            $tropicalStart
        );
        $this->seedReadingActivities($tropicalLoanId, $tropicalStart, 3);
        DB::table('aktivitas_baca')->updateOrInsert(
            [
                'id_peminjaman' => $tropicalLoanId,
                'tanggal_baca' => $activityDate->toDateString(),
            ],
            [
                'durasi_menit' => 30,
                'status_syarat' => true,
                'created_at' => $activityDate->copy()->setTime(19, 0)->toDateTimeString(),
                'updated_at' => $activityDate->copy()->setTime(19, 0)->toDateTimeString(),
            ]
        );
        $this->upsertNotification(
            $member->id_pengguna,
            'aktivitas_baca',
            "Riwayat membaca \"{$books[10]->judul}\" telah tersimpan di akun Anda.",
            $periodStart->copy()->subDay()->setTime(19, 0),
            true,
            $books[10]->id_buku
        );

        $newLoanStart = $periodStart->copy()->addHours(8)->addMinutes(30);
        $this->upsertLoan(
            $member->id_pengguna,
            $books[33]->id_buku,
            $newLoanStart,
            $newLoanStart->copy()->addWeeks(2),
            'aktif',
            $newLoanStart,
            $newLoanStart
        );

        for ($bookIndex = 0; $bookIndex < 6; $bookIndex++) {
            $joinedAt = $periodStart->copy()->addMinutes($bookIndex + 1);
            $this->seedWaitingQueue($member, $books[$bookIndex], $joinedAt);
            $this->upsertNotification(
                $member->id_pengguna,
                'antrean_menunggu',
                "Anda berada di posisi 2 dalam antrean \"{$books[$bookIndex]->judul}\".",
                $joinedAt,
                false,
                $books[$bookIndex]->id_buku
            );
        }

        $cancelledQueueTime = $periodStart->copy()->addMinutes(7);
        $this->seedWaitingQueue($member, $books[6], $cancelledQueueTime, 'batal');
        $this->upsertNotification(
            $member->id_pengguna,
            'antrean_menunggu',
            "Anda berada di posisi 4 dalam antrean \"{$books[6]->judul}\".",
            $cancelledQueueTime,
            false,
            $books[6]->id_buku
        );
    }

    private function seedWaitingQueue(
        User $member,
        KoleksiBuku $book,
        Carbon $joinedAt,
        string $status = 'menunggu'
    ): void {
        DB::table('antrean')->insert([
            'id_pengguna' => $member->id_pengguna,
            'id_buku' => $book->id_buku,
            'nomor_urut' => ((int) DB::table('antrean')->where('id_buku', $book->id_buku)->max('nomor_urut')) + 1,
            'waktu_masuk' => $joinedAt->toDateTimeString(),
            'status_antrean' => $status,
            'created_at' => $joinedAt->toDateTimeString(),
            'updated_at' => $joinedAt->toDateTimeString(),
        ]);
    }

    private function clearDemoActivity(array $members): void
    {
        $memberIds = array_map(fn (User $member) => $member->id_pengguna, $members);

        DB::table('notifikasi')->whereIn('id_pengguna', $memberIds)->delete();
        DB::table('antrean')->whereIn('id_pengguna', $memberIds)->delete();
        DB::table('peminjaman')->whereIn('id_pengguna', $memberIds)->delete();
    }

    private function seedCompletedLoans(array $members, array $books, Carbon $periodStart): void
    {
        foreach ($members as $index => $member) {
            $book = $books[$index];
            $startedAt = $periodStart->copy()->subDays(30 + ($index % 15))->addHours($index % 9);
            $returnedAt = $startedAt->copy()->addDays(5 + ($index % 5));
            $loanId = $this->upsertLoan(
                $member->id_pengguna,
                $book->id_buku,
                $startedAt,
                $startedAt->copy()->addWeeks(2),
                'selesai',
                $startedAt,
                $returnedAt
            );

            $this->seedReadingActivities($loanId, $startedAt, 4);
        }
    }

    private function seedActiveLoans(array $members, array $books, Carbon $periodStart): void
    {
        for ($index = 0; $index < 40; $index++) {
            $bookIndex = $index < 21
                ? intdiv($index, 3)
                : 7 + (($index - 21) % 28);
            $member = $members[$index];
            $book = $books[$bookIndex];
            $ageInDays = $index === 0
                ? 13
                : ($index < 21 && $index % 3 === 0 ? 8 : 1 + ($index % 3));
            $startedAt = $periodStart->copy()->subDays($ageInDays)->addHours(8 + ($index % 8));
            $loanId = $this->upsertLoan(
                $member->id_pengguna,
                $book->id_buku,
                $startedAt,
                $startedAt->copy()->addWeeks(2),
                'aktif',
                $startedAt,
                $startedAt
            );

            $completedCycles = $index === 0 ? 4 : intdiv($ageInDays, 3);
            $this->seedReadingActivities($loanId, $startedAt, $completedCycles);
        }
    }

    private function seedWaitingQueues(array $members, array $books, Carbon $periodStart): void
    {
        $entries = [];
        for ($bookIndex = 0; $bookIndex < 6; $bookIndex++) {
            $entries[] = [
                'book_index' => $bookIndex,
                'member_index' => 42,
                'joined_at' => $periodStart->copy()->subHours(6 - $bookIndex),
            ];
        }
        $entries[] = ['book_index' => 6, 'member_index' => 88, 'joined_at' => $periodStart->copy()->subHours(2)];
        $entries[] = ['book_index' => 6, 'member_index' => 89, 'joined_at' => $periodStart->copy()->subHour()];

        foreach ($entries as $entry) {
            $book = $books[$entry['book_index']];
            $member = $members[$entry['member_index']];
            $joinedAt = $entry['joined_at'];
            $existingQueue = DB::table('antrean')
                ->where('id_pengguna', $member->id_pengguna)
                ->where('id_buku', $book->id_buku)
                ->whereIn('status_antrean', ['menunggu', 'diproses'])
                ->exists();

            if ($existingQueue) {
                continue;
            }

            $queueNumber = ((int) DB::table('antrean')->where('id_buku', $book->id_buku)->max('nomor_urut')) + 1;
            DB::table('antrean')->insert([
                'id_pengguna' => $member->id_pengguna,
                'id_buku' => $book->id_buku,
                'nomor_urut' => $queueNumber,
                'waktu_masuk' => $joinedAt->toDateTimeString(),
                'status_antrean' => 'menunggu',
                'created_at' => $joinedAt->toDateTimeString(),
                'updated_at' => $joinedAt->toDateTimeString(),
            ]);
        }

        $lastBook = $books[6];
        $lastMember = $members[90];
        $lastJoinedAt = $periodStart->copy();
        $existingQueue = DB::table('antrean')
            ->where('id_pengguna', $lastMember->id_pengguna)
            ->where('id_buku', $lastBook->id_buku)
            ->whereIn('status_antrean', ['menunggu', 'diproses'])
            ->exists();

        if (! $existingQueue) {
            DB::table('antrean')->insert([
                'id_pengguna' => $lastMember->id_pengguna,
                'id_buku' => $lastBook->id_buku,
                'nomor_urut' => ((int) DB::table('antrean')->where('id_buku', $lastBook->id_buku)->max('nomor_urut')) + 1,
                'waktu_masuk' => $lastJoinedAt->toDateTimeString(),
                'status_antrean' => 'menunggu',
                'created_at' => $lastJoinedAt->toDateTimeString(),
                'updated_at' => $lastJoinedAt->toDateTimeString(),
            ]);
        }
    }

    private function seedNotifications(array $members, array $books, Carbon $periodStart): void
    {
        foreach ($members as $index => $member) {
            $book = $books[$index];
            $sentAt = $periodStart->copy()->subDays(1)->addHours($index % 8);
            $message = "Riwayat membaca \"{$book->judul}\" telah tersimpan di akun Anda.";
            $this->upsertNotification(
                $member->id_pengguna,
                'aktivitas_baca',
                $message,
                $sentAt,
                $index % 4 === 0,
                $book->id_buku
            );
        }

        for ($bookIndex = 0; $bookIndex < 6; $bookIndex++) {
            $book = $books[$bookIndex];
            $member = $members[42];
            $sentAt = $periodStart->copy()->subHours(6 - $bookIndex);
            $this->upsertNotification(
                $member->id_pengguna,
                'antrean_menunggu',
                "Anda berada di posisi 1 dalam antrean \"{$book->judul}\".",
                $sentAt,
                false,
                $book->id_buku
            );
        }

        foreach ([88, 89, 90] as $position => $memberIndex) {
            $book = $books[6];
            $sentAt = $periodStart->copy()->subHours(2 - $position);
            $this->upsertNotification(
                $members[$memberIndex]->id_pengguna,
                'antrean_menunggu',
                'Anda berada di posisi '.($position + 1)." dalam antrean \"{$book->judul}\".",
                $sentAt,
                false,
                $book->id_buku
            );
        }

        $member = $members[0];
        $book = $books[0];
        $loanStart = Carbon::parse($member->peminjaman()
            ->where('id_buku', $book->id_buku)
            ->where('status_pinjam', 'aktif')
            ->value('tanggal_mulai'));

        for ($cycle = 0; $cycle < 4; $cycle++) {
            for ($day = 0; $day < 2; $day++) {
                $sentAt = $loanStart->copy()->startOfDay()->addDays($cycle * 3 + $day)->setTime(8, 0);
                $this->upsertNotification(
                    $member->id_pengguna,
                    'pengingat_durasi',
                    "Durasi 30 menit belum terpenuhi untuk \"{$book->judul}\".",
                    $sentAt,
                    true,
                    $book->id_buku
                );
            }
        }

        foreach ([12, 13] as $day) {
            $sentAt = $loanStart->copy()->startOfDay()->addDays($day)->setTime(8, 0);
            if ($sentAt->isFuture()) {
                continue;
            }

            $this->upsertNotification(
                $member->id_pengguna,
                'masa_pinjam_hampir_habis',
                "Hak akses pinjam \"{$book->judul}\" akan segera selesai.",
                $sentAt,
                false,
                $book->id_buku
            );
        }
    }

    private function seedReadingActivities(int $loanId, Carbon $startedAt, int $completedCycles): void
    {
        for ($cycle = 0; $cycle < $completedCycles; $cycle++) {
            $date = $startedAt->copy()->startOfDay()->addDays($cycle * 3 + 2);
            DB::table('aktivitas_baca')->updateOrInsert(
                [
                    'id_peminjaman' => $loanId,
                    'tanggal_baca' => $date->toDateString(),
                ],
                [
                    'durasi_menit' => 30,
                    'status_syarat' => true,
                    'created_at' => $date->copy()->setTime(19, 0)->toDateTimeString(),
                    'updated_at' => $date->copy()->setTime(19, 0)->toDateTimeString(),
                ]
            );
        }
    }

    private function upsertLoan(
        int $userId,
        int $bookId,
        Carbon $startedAt,
        Carbon $dueAt,
        string $status,
        Carbon $createdAt,
        Carbon $updatedAt
    ): int {
        $startedAtValue = $startedAt->toDateTimeString();
        $existingId = DB::table('peminjaman')
            ->where('id_pengguna', $userId)
            ->where('id_buku', $bookId)
            ->where('tanggal_mulai', $startedAtValue)
            ->value('id_peminjaman');
        $values = [
            'tanggal_jatuh_tempo' => $dueAt->toDateTimeString(),
            'status_pinjam' => $status,
            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => $updatedAt->toDateTimeString(),
        ];

        if ($existingId) {
            DB::table('peminjaman')->where('id_peminjaman', $existingId)->update($values);

            return (int) $existingId;
        }

        return (int) DB::table('peminjaman')->insertGetId([
            'id_pengguna' => $userId,
            'id_buku' => $bookId,
            'tanggal_mulai' => $startedAtValue,
            ...$values,
        ], 'id_peminjaman');
    }

    private function upsertNotification(
        int $userId,
        string $type,
        string $message,
        Carbon $sentAt,
        bool $read,
        ?int $bookId = null
    ): void {
        $existingId = DB::table('notifikasi')
            ->where('id_pengguna', $userId)
            ->where('jenis_notifikasi', $type)
            ->where('isi_pesan', $message)
            ->whereDate('waktu_kirim', $sentAt->toDateString())
            ->value('id_notifikasi');
        $values = [
            'id_buku' => $bookId,
            'waktu_kirim' => $sentAt->toDateTimeString(),
            'status_baca' => $read,
            'updated_at' => $sentAt->toDateTimeString(),
        ];

        if ($existingId) {
            DB::table('notifikasi')->where('id_notifikasi', $existingId)->update($values);

            return;
        }

        DB::table('notifikasi')->insert([
            'id_pengguna' => $userId,
            'id_buku' => $bookId,
            'jenis_notifikasi' => $type,
            'isi_pesan' => $message,
            ...$values,
            'created_at' => $sentAt->toDateTimeString(),
        ]);
    }

    private function authorFor(int $index): string
    {
        $authors = [
            'Nadia Prameswari', 'Bima Adinata', 'Ratih Kusuma', 'Rendra Mahesa',
            'Dian Purnama', 'Ayu Sekarini', 'Fikri Ramadhan', 'Mira Andalas',
            'Tegar Wicaksana', 'Sari Paramita', 'Gilang Permadi', 'Putri Maharani',
        ];

        return $authors[$index % count($authors)];
    }
}
