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

    public function sale()
    {
        return $this->belongsTo(Sale::class);
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

        $entries = $sheetDate === null
            ? self::all()
            : self::where(fn ($q) => $q->where('sheet_date', $sheetDate)->orWhereNull('sheet_date'))->get();

        foreach ($entries as $entry) {
            $balances[$entry->name] = ($balances[$entry->name] ?? 0)
                + ($entry->type === 'tambah' ? $entry->amount : -$entry->amount);
        }

        ksort($balances);

        return $balances;
    }
}
