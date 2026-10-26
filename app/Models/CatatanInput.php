<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatatanInput extends Model
{
    protected $table = 'catatan_input';

    protected $fillable = [
        'metode', 'tab', 'perangkat', 'teks_mentah', 'teks_normal',
        'json_urai', 'json_akhir', 'json_cocok',
        'durasi_ms', 'mulai_pada', 'penjualan_id', 'status', 'kesalahan',
    ];

    protected $casts = [
        'json_urai' => 'array',
        'json_akhir' => 'array',
        'json_cocok' => 'array',
        'mulai_pada' => 'datetime',
        'durasi_ms' => 'integer',
    ];
}
