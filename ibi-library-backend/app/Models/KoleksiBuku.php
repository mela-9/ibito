<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KoleksiBuku extends Model
{
    protected $table = 'koleksi_buku';

    protected $primaryKey = 'id_buku';

    protected $fillable = [
        'judul',
        'penulis',
        'kategori',
        'tahun_terbit',
        'kata_kunci',
        'ringkasan',
        'berkas_ebook',
        'kuota_maks',
    ];

    protected function casts(): array
    {
        return [
            'tahun_terbit' => 'integer',
            'kata_kunci' => 'array',
        ];
    }

    public function peminjamanAktif()
    {
        return $this->hasMany(Peminjaman::class, 'id_buku', 'id_buku')
            ->where('status_pinjam', 'aktif');
    }

    public function antrean()
    {
        return $this->hasMany(Antrean::class, 'id_buku', 'id_buku')
            ->where('status_antrean', 'menunggu')
            ->orderBy('waktu_masuk');
    }
}
