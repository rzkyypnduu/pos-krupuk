<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPenjualan extends Model
{
    protected $table = 'item_penjualan';

    protected $fillable = ['penjualan_id', 'produk_id', 'nama', 'jumlah', 'harga'];

    protected $casts = [
        'jumlah' => 'float',
        'harga' => 'integer',
    ];
}
