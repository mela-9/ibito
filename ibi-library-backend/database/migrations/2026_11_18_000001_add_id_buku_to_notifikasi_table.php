<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->foreignId('id_buku')
                ->nullable()
                ->after('id_pengguna')
                ->constrained('koleksi_buku', 'id_buku')
                ->nullOnDelete();
        });

        // Backfill existing notifications with matching book titles in isi_pesan
        $books = DB::table('koleksi_buku')->select('id_buku', 'judul')->get();
        foreach ($books as $book) {
            DB::table('notifikasi')
                ->whereNull('id_buku')
                ->where('isi_pesan', 'like', '%"'.$book->judul.'"%')
                ->update(['id_buku' => $book->id_buku]);
        }
    }

    public function down(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['id_buku']);
            }
            $table->dropColumn('id_buku');
        });
    }
};
