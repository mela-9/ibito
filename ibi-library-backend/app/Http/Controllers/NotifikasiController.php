<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request)
    {
        return Notifikasi::query()
            ->where('id_pengguna', $request->user()->id_pengguna)
            ->with('buku:id_buku,judul,penulis,kategori')
            ->latest('waktu_kirim')
            ->limit(100)
            ->get();
    }

    public function read(Request $request, Notifikasi $notifikasi)
    {
        abort_unless($notifikasi->id_pengguna === $request->user()->id_pengguna, 403);
        $notifikasi->update(['status_baca' => true]);

        return response()->json(['data' => $notifikasi->refresh()]);
    }
}
