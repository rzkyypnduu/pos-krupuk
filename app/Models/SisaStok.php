<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SisaStok extends Model
{
    protected $table = 'sisa_stok';

    protected $fillable = ['tanggal', 'nama', 'jumlah', 'harga'];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah' => 'float',
        'harga' => 'integer',
    ];

    public function subtotal(): float
    {
        return $this->jumlah * $this->harga;
    }
}
