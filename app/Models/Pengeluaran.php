<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengeluaran extends Model
{
    protected $table = 'pengeluaran';

    protected $fillable = ['tanggal', 'jumlah', 'catatan'];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah' => 'integer',
    ];
}
