<?php

namespace App\Http\Controllers;

use App\Models\Antrean;
use App\Models\KoleksiBuku;
use App\Models\Peminjaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AntreanController extends Controller
{
    public function mine(Request $request)
    {
        return Antrean::query()
            ->with('buku')
            ->where('id_pengguna', $request->user()->id_pengguna)
            ->where('status_antrean', 'menunggu')
            ->orderBy('waktu_masuk')
            ->get()
            ->map(function (Antrean $queue) {
                $ahead = Antrean::query()
                    ->where('id_buku', $queue->id_buku)
                    ->where('status_antrean', 'menunggu')
                    ->where(function ($query) use ($queue) {
                        $query->where('waktu_masuk', '<', $queue->waktu_masuk)
                            ->orWhere(function ($tie) use ($queue) {
                                $tie->where('waktu_masuk', $queue->waktu_masuk)
                                    ->where('id_antrean', '<=', $queue->id_antrean);
                            });
                    })
                    ->count();
                $active = Peminjaman::query()
                    ->where('id_buku', $queue->id_buku)
                    ->where('status_pinjam', 'aktif')
                    ->count();
                $quota = max(1, $queue->buku->kuota_maks);
                $queue->posisi = $ahead;
                $queue->estimasi_hari = (int) (ceil(max(0, $active + $ahead - $quota) / $quota) * 14);

                return $queue;
            });
    }

    public function forBook(KoleksiBuku $koleksiBuku)
    {
        $position = 0;

        return Antrean::query()
            ->with('pengguna:id_pengguna,nama')
            ->where('id_buku', $koleksiBuku->id_buku)
            ->orderBy('waktu_masuk')
            ->orderBy('id_antrean')
            ->get()
            ->map(function (Antrean $queue) use (&$position) {
                if ($queue->status_antrean === 'menunggu') {
                    $position++;
                }

                return [
                    'id_antrean' => $queue->id_antrean,
                    'nama_pengguna' => $queue->pengguna->nama,
                    'nomor_urut' => $queue->nomor_urut,
                    'posisi' => $queue->status_antrean === 'menunggu' ? $position : null,
                    'waktu_masuk' => $queue->waktu_masuk,
                    'status_antrean' => $queue->status_antrean,
                    'updated_at' => $queue->updated_at,
                ];
            });
    }

    public function destroy(Request $request, Antrean $antrean)
    {
        abort_unless($antrean->id_pengguna === $request->user()->id_pengguna, 403);
        abort_unless($antrean->status_antrean === 'menunggu', 422, 'Antrean ini tidak dapat dibatalkan.');

        DB::transaction(function () use ($antrean) {
            $antrean->update(['status_antrean' => 'batal']);
        });

        return response()->noContent();
    }
}
