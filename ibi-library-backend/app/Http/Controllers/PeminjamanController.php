<?php

namespace App\Http\Controllers;

use App\Models\AktivitasBaca;
use App\Models\Antrean;
use App\Models\KoleksiBuku;
use App\Models\Peminjaman;
use App\Services\CirculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeminjamanController extends Controller
{
    public function index(Request $request, CirculationService $circulation)
    {
        return Peminjaman::query()
            ->with('buku')
            ->where('id_pengguna', $request->user()->id_pengguna)
            ->where('status_pinjam', 'aktif')
            ->latest('tanggal_mulai')
            ->get()
            ->map(function (Peminjaman $loan) use ($circulation) {
                $window = $circulation->readingWindow($loan);
                $today = now()->startOfDay();
                $loan->total_menit_3_hari = (int) $loan->aktivitasBaca()
                    ->whereDate('tanggal_baca', '>=', $window['start']->toDateString())
                    ->whereDate('tanggal_baca', '<=', $window['end']->toDateString())
                    ->sum('durasi_menit');
                $loan->target_membaca_aktif = $window['target_active'];
                $loan->hari_sampai_batas_baca = max(0, (int) $today->diffInDays($window['end']->copy()->startOfDay(), false));
                $loan->hari_sampai_jatuh_tempo = (int) $today->diffInDays($loan->tanggal_jatuh_tempo->copy()->startOfDay(), false);

                return $loan;
            });
    }

    public function store(Request $request, CirculationService $circulation)
    {
        $validated = $request->validate([
            'id_buku' => ['required', 'integer', 'exists:koleksi_buku,id_buku'],
        ]);
        $result = $circulation->borrowOrQueue($request->user(), $validated['id_buku']);

        return response()->json($result, $result['status'] === 'borrowed' ? 201 : 200);
    }

    public function return(Peminjaman $peminjaman, Request $request, CirculationService $circulation)
    {
        abort_unless($peminjaman->id_pengguna === $request->user()->id_pengguna, 403);

        return response()->json([
            'data' => $circulation->returnLoan($peminjaman),
        ]);
    }

    public function logReading(Request $request, CirculationService $circulation)
    {
        $validated = $request->validate([
            'id_peminjaman' => ['required', 'integer', 'exists:peminjaman,id_peminjaman'],
            'durasi_menit' => ['required', 'integer', 'min:1', 'max:5'],
        ]);
        $loan = Peminjaman::query()
            ->where('id_peminjaman', $validated['id_peminjaman'])
            ->where('id_pengguna', $request->user()->id_pengguna)
            ->where('status_pinjam', 'aktif')
            ->firstOrFail();

        $window = $circulation->readingWindow($loan);
        $activity = DB::transaction(function () use ($loan, $validated, $window) {
            Peminjaman::query()
                ->where('id_peminjaman', $loan->id_peminjaman)
                ->lockForUpdate()
                ->firstOrFail();
            $activity = AktivitasBaca::query()
                ->where('id_peminjaman', $loan->id_peminjaman)
                ->whereDate('tanggal_baca', now()->toDateString())
                ->first();

            if (! $activity) {
                $activity = AktivitasBaca::query()->create([
                    'id_peminjaman' => $loan->id_peminjaman,
                    'tanggal_baca' => now()->toDateString(),
                    'durasi_menit' => 0,
                ]);
            }

            $activity->increment('durasi_menit', $validated['durasi_menit']);
            $minutes = AktivitasBaca::query()
                ->where('id_peminjaman', $loan->id_peminjaman)
                ->whereDate('tanggal_baca', '>=', $window['start']->toDateString())
                ->whereDate('tanggal_baca', '<=', $window['end']->toDateString())
                ->sum('durasi_menit');
            $activity->update([
                'status_syarat' => $window['target_active'] && $minutes >= 30,
            ]);

            return $activity->refresh();
        });

        return response()->json([
            'data' => $activity,
            'total_menit_3_hari' => (int) AktivitasBaca::query()
                ->where('id_peminjaman', $loan->id_peminjaman)
                ->whereDate('tanggal_baca', '>=', $window['start']->toDateString())
                ->whereDate('tanggal_baca', '<=', $window['end']->toDateString())
                ->sum('durasi_menit'),
            'status_syarat' => (bool) $activity->status_syarat,
            'target_membaca_aktif' => $window['target_active'],
        ]);
    }

    public function circulation()
    {
        abort_unless(request()->user()->peran === 'pustakawan', 403);

        return response()->json([
            'total_buku' => KoleksiBuku::query()->count(),
            'peminjaman_aktif' => Peminjaman::query()->where('status_pinjam', 'aktif')->count(),
            'antrean_menunggu' => Antrean::query()->where('status_antrean', 'menunggu')->count(),
            'peminjaman_selesai_bulan_ini' => Peminjaman::query()
                ->whereIn('status_pinjam', ['selesai', 'timeout'])
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count(),
            'sirkulasi_bulanan' => collect(range(5, 0))->map(function (int $monthsAgo) {
                $month = now()->startOfMonth()->subMonths($monthsAgo);

                return [
                    'bulan' => $month->translatedFormat('M'),
                    'total' => Peminjaman::query()
                        ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                        ->count(),
                ];
            })->values(),
            'per_buku' => KoleksiBuku::query()
                ->withCount([
                    'peminjamanAktif as peminjam_aktif',
                    'antrean as antrean_menunggu',
                ])
                ->get(),
        ]);
    }
}
