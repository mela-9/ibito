<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Peminjaman extends Model
{
    protected $table = 'peminjaman';

    protected $primaryKey = 'id_peminjaman';

    protected $fillable = [
        'id_pengguna',
        'id_buku',
        'tanggal_mulai',
        'tanggal_jatuh_tempo',
        'status_pinjam',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'datetime',
            'tanggal_jatuh_tempo' => 'datetime',
        ];
    }

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'id_pengguna', 'id_pengguna');
    }

    public function buku()
    {
        return $this->belongsTo(KoleksiBuku::class, 'id_buku', 'id_buku');
    }

    public function aktivitasBaca()
    {
        return $this->hasMany(AktivitasBaca::class, 'id_peminjaman', 'id_peminjaman');
    }
}
