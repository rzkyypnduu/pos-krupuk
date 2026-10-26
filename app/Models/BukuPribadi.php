<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BukuPribadi extends Model
{
    protected $table = 'buku_pribadi';

    protected $fillable = ['tanggal', 'nama', 'jumlah'];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah' => 'integer',
    ];
}
