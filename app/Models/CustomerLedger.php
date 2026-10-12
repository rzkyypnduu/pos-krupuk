<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLedger extends Model
{
    protected $fillable = ['date', 'sheet_date', 'name', 'amount', 'type', 'note', 'sale_id'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'integer',
    ];

    /**
     * Entri milik sebuah lembar hasil: tanggal itu + entri legacy tanpa lembar;
     * lewat null = semua entri (tanpa filter). Satu-satunya rumus filter sheet_date.
     */
    public function scopeForSheet($query, ?string $sheetDate)
    {
        if ($sheetDate === null) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('sheet_date', $sheetDate)->orWhereNull('sheet_date'));
    }
}
