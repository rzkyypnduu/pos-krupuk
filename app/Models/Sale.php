<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = ['date', 'name', 'raw_total', 'rounded_total', 'paid', 'paid_kemarin', 'diff', 'note', 'is_paid_btn_clicked'];

    protected $casts = [
        'date' => 'date',
        'raw_total' => 'integer',
        'rounded_total' => 'integer',
        'paid' => 'integer',
        'paid_kemarin' => 'integer',
        'diff' => 'integer',
        'is_paid_btn_clicked' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
