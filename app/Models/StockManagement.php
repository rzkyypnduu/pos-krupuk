<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockManagement extends Model
{
    protected $table = 'stock_managements';

    protected $fillable = ['date', 'name', 'qty', 'price', 'batches'];

    protected $casts = [
        'date' => 'date',
        'qty' => 'float',
        'price' => 'integer',
        'batches' => 'array',
    ];

    public function subtotal(): float
    {
        $sum = 0;
        foreach ($this->batches ?? [] as $batch) {
            $sacks = $batch['sacks'] ?? [];
            $batchQty = array_sum($sacks);
            $batchPrice = $batch['price'] ?? $this->price ?? 0;
            $sum += $batchQty * $batchPrice;
        }

        return $sum ?: $this->qty * $this->price;
    }

    public static function totalQty(array $batches): float
    {
        $sum = 0;
        foreach ($batches as $batch) {
            $sum += array_sum($batch['sacks'] ?? []);
        }

        return $sum;
    }

    public static function totalValue(array $batches, int $defaultPrice = 0): float
    {
        $sum = 0;
        foreach ($batches as $batch) {
            $batchQty = array_sum($batch['sacks'] ?? []);
            $batchPrice = $batch['price'] ?? $defaultPrice;
            $sum += $batchQty * $batchPrice;
        }

        return $sum;
    }
}
