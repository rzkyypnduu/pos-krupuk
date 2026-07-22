<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = ['date', 'name', 'raw_total', 'rounded_total', 'paid', 'diff', 'note', 'is_paid_btn_clicked'];

    protected $casts = [
        'date' => 'date',
        'raw_total' => 'integer',
        'rounded_total' => 'integer',
        'paid' => 'integer',
        'diff' => 'integer',
        'is_paid_btn_clicked' => 'boolean',
    ];

    public static function roundTotal(int $total): int
    {
        $thousands = intdiv($total, 1000) * 1000;
        $remainder = $total - $thousands;

        return $remainder < 500 ? $thousands : $thousands + 1000;
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(CustomerLedger::class, 'sale_id');
    }
}
