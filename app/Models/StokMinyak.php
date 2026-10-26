<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokMinyak extends Model
{
    protected $table = 'stok_minyak';

    protected $fillable = ['tanggal', 'jumlah', 'harga'];

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
