<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLedger extends Model
{
    protected $fillable = ['date', 'name', 'amount', 'type', 'note', 'sale_id'];

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
     * @return array<string, int>
     */
    public static function balances(): array
    {
        $balances = [];

        foreach (self::all() as $entry) {
            $balances[$entry->name] = ($balances[$entry->name] ?? 0)
                + ($entry->type === 'tambah' ? $entry->amount : -$entry->amount);
        }

        ksort($balances);

        return $balances;
    }
}
