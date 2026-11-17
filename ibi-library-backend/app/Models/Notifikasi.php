<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'notifikasi';

    protected $primaryKey = 'id_notifikasi';

    protected $fillable = [
        'id_pengguna',
        'jenis_notifikasi',
        'isi_pesan',
        'waktu_kirim',
        'status_baca',
    ];

    protected function casts(): array
    {
        return [
            'waktu_kirim' => 'datetime',
            'status_baca' => 'boolean',
        ];
    }
}
