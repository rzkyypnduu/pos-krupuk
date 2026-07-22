<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'price'];

    protected $casts = [
        'price' => 'integer',
    ];

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }
}
