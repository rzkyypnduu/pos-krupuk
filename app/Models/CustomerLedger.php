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

    /**
     * Saldo hutang per pelanggan (dikelompokkan berdasarkan nama).
     * Nilai positif = pelanggan punya hutang, negatif = kelebihan bayar.
     *
     * @param string|null $sheetDate Lembar hasil tanggal aktif — hanya entri milik
     *                               tanggal itu (serta entri tanpa lembar/legacy)
     *                               yang dihitung; lewat null = semua entri.
     * @return array<string, int>
     */
    public static function balances(?string $sheetDate = null): array
    {
        $balances = [];

        $entries = static::forSheet($sheetDate)->get();

        foreach ($entries as $entry) {
            $balances[$entry->name] = ($balances[$entry->name] ?? 0)
                + ($entry->type === 'tambah' ? $entry->amount : -$entry->amount);
        }

        ksort($balances);

        return $balances;
    }
}
