<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OilStock extends Model
{
    protected $fillable = ['date', 'qty', 'price'];

    protected $casts = [
        'date' => 'date',
        'qty' => 'float',
        'price' => 'integer',
    ];

    public function subtotal(): float
    {
        return $this->qty * $this->price;
    }
}
