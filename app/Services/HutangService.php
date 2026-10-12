<?php

namespace App\Services;

use App\Models\CustomerLedger;
use App\Models\Sale;

/**
 * Perhitungan sisa hutang pelanggan untuk tab Hasil (rekap & detail).
 * Ledger bersifat catatan manual (tambah/bayar) — sisa = Σ tambah − Σ bayar,
 * tanpa alokasi pembayaran ke entri tertentu.
 */
class HutangService
{
    /**
     * Sisa hutang satu pelanggan pada sebuah lembar tanggal:
     * totalSisa (selisih positif = masih berhutang) dan deposit
     * (kelebihan bayar yang melebihi seluruh tambahan).
     */
    public function processCustomerDebts(string $name, ?string $sheetDate = null): array
    {
        $entries = CustomerLedger::forSheet($sheetDate)
            ->where('name', $name)
            ->get();
        $tambah = 0;
        $bayar = 0;
        foreach ($entries as $l) {
            if ($l->type === 'tambah') {
                $tambah += $l->amount;
            } else {
                $bayar += $l->amount;
            }
        }
        $net = $tambah - $bayar;
        $totalSisa = max(0, $net);
        $deposit = max(0, -$net);

        return compact('totalSisa', 'deposit');
    }

    /** Riwayat lengkap satu pelanggan (untuk panel detail) dengan saldo berjalan. */
    public function hpDetailEntries(string $name, ?string $sheetDate = null)
    {
        $entries = CustomerLedger::forSheet($sheetDate)
            ->where('name', $name)
            ->orderBy('date')->orderBy('id')->get();
        $running = 0;
        $sales = Sale::whereIn('id', $entries->pluck('sale_id')->filter()->unique())->get()->keyBy('id');

        return $entries->map(function ($l) use (&$running, $sales) {
            $running += $l->type === 'tambah' ? $l->amount : -$l->amount;

            return (object) [
                'id' => $l->id, 'date' => $l->date->format('Y-m-d'),
                'type' => $l->type, 'amount' => $l->amount, 'running' => $running,
                'note' => $l->note, 'sale_id' => $l->sale_id,
                'paid' => $l->sale_id && isset($sales[$l->sale_id]) ? $sales[$l->sale_id]->paid : null,
            ];
        });
    }
}
