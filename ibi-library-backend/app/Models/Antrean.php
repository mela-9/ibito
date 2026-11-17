<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Antrean extends Model
{
    protected $table = 'antrean';

    protected $primaryKey = 'id_antrean';

    protected $fillable = [
        'id_pengguna',
        'id_buku',
        'nomor_urut',
        'waktu_masuk',
        'status_antrean',
    ];

    protected function casts(): array
    {
        return ['waktu_masuk' => 'datetime'];
    }

    public function buku()
    {
        return $this->belongsTo(KoleksiBuku::class, 'id_buku', 'id_buku');
    }

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'id_pengguna', 'id_pengguna');
    }
}
