<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $table) {
            $table->id('id_pengguna');
            $table->string('nama');
            $table->string('email')->unique();
            $table->string('kata_sandi');
            $table->enum('peran', ['anggota', 'pustakawan'])->default('anggota');
            $table->boolean('status_verifikasi')->default(false);
            $table->timestamps();
        });

        Schema::create('koleksi_buku', function (Blueprint $table) {
            $table->id('id_buku');
            $table->string('judul');
            $table->string('penulis');
            $table->string('kategori');
            $table->text('ringkasan')->nullable();
            $table->string('berkas_ebook')->nullable();
            $table->unsignedInteger('kuota_maks')->default(30);
            $table->timestamps();
        });

        Schema::create('peminjaman', function (Blueprint $table) {
            $table->id('id_peminjaman');
            $table->foreignId('id_pengguna')->constrained('pengguna', 'id_pengguna')->cascadeOnDelete();
            $table->foreignId('id_buku')->constrained('koleksi_buku', 'id_buku')->cascadeOnDelete();
            $table->dateTime('tanggal_mulai');
            $table->dateTime('tanggal_jatuh_tempo');
            $table->enum('status_pinjam', ['aktif', 'selesai', 'timeout'])->default('aktif');
            $table->timestamps();
            $table->index(['id_buku', 'status_pinjam']);
            $table->index(['id_pengguna', 'status_pinjam']);
        });

        Schema::create('antrean', function (Blueprint $table) {
            $table->id('id_antrean');
            $table->foreignId('id_pengguna')->constrained('pengguna', 'id_pengguna')->cascadeOnDelete();
            $table->foreignId('id_buku')->constrained('koleksi_buku', 'id_buku')->cascadeOnDelete();
            $table->unsignedInteger('nomor_urut');
            $table->dateTime('waktu_masuk');
            $table->enum('status_antrean', ['menunggu', 'diproses', 'batal'])->default('menunggu');
            $table->timestamps();
            $table->index(['id_buku', 'status_antrean', 'waktu_masuk']);
        });

        Schema::create('aktivitas_baca', function (Blueprint $table) {
            $table->id('id_aktivitas');
            $table->foreignId('id_peminjaman')->constrained('peminjaman', 'id_peminjaman')->cascadeOnDelete();
            $table->date('tanggal_baca');
            $table->unsignedInteger('durasi_menit')->default(0);
            $table->boolean('status_syarat')->default(false);
            $table->timestamps();
            $table->unique(['id_peminjaman', 'tanggal_baca']);
        });

        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id('id_notifikasi');
            $table->foreignId('id_pengguna')->constrained('pengguna', 'id_pengguna')->cascadeOnDelete();
            $table->string('jenis_notifikasi');
            $table->text('isi_pesan');
            $table->dateTime('waktu_kirim');
            $table->boolean('status_baca')->default(false);
            $table->timestamps();
            $table->index(['id_pengguna', 'status_baca']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('aktivitas_baca');
        Schema::dropIfExists('antrean');
        Schema::dropIfExists('peminjaman');
        Schema::dropIfExists('koleksi_buku');
        Schema::dropIfExists('pengguna');
    }
};
