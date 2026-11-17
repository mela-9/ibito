<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AktivitasBaca extends Model
{
    protected $table = 'aktivitas_baca';

    protected $primaryKey = 'id_aktivitas';

    protected $fillable = [
        'id_peminjaman',
        'tanggal_baca',
        'durasi_menit',
        'status_syarat',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_baca' => 'date',
            'status_syarat' => 'boolean',
        ];
    }
}
