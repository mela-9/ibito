<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('koleksi_buku', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_terbit')->nullable();
            $table->json('kata_kunci')->nullable();
        });

        DB::table('koleksi_buku')
            ->select('id_buku', 'judul', 'kategori')
            ->orderBy('id_buku')
            ->chunkById(100, function ($books) {
                foreach ($books as $book) {
                    DB::table('koleksi_buku')
                        ->where('id_buku', $book->id_buku)
                        ->update([
                            'tahun_terbit' => 2000 + (($book->id_buku * 7) % 26),
                            'kata_kunci' => json_encode(array_values(array_unique([
                                $book->judul,
                                $book->kategori,
                            ]))),
                        ]);
                }
            }, 'id_buku');
    }

    public function down(): void
    {
        Schema::table('koleksi_buku', function (Blueprint $table) {
            $table->dropColumn(['tahun_terbit', 'kata_kunci']);
        });
    }
};
