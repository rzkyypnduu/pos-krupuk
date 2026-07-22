<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'name', 'qty', 'price'];

    protected $casts = [
        'qty' => 'float',
        'price' => 'integer',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function subtotal(): float
    {
        return $this->qty * $this->price;
    }
}
