<?php

namespace App\Services;

use App\Models\CustomerLedger;
use App\Models\Sale;

/**
 * Perhitungan sisa hutang pelanggan untuk tab Hasil (rekap & detail).
 * Pembayaran memotong entri terbaru dulu (LIFO) — sejajar preview modal Bayar.
 */
class HutangService
{
    /**
     * Sisa hutang satu pelanggan pada sebuah lembar tanggal:
     * activeDebts (id, amount, remaining, date), totalSisa, dan deposit
     * (kelebihan bayar yang melebihi seluruh entri).
     */
    public function processCustomerDebts(string $name, ?string $sheetDate = null): array
    {
        $query = CustomerLedger::where('name', $name);
        if ($sheetDate !== null) {
            $query->where(fn ($q) => $q->where('sheet_date', $sheetDate)->orWhereNull('sheet_date'));
        }
        $entries = $query->orderBy('date')->orderBy('id')->get();
        $debts = [];
        $deposit = 0;
        foreach ($entries as $l) {
            if ($l->type === 'tambah') {
                $debts[] = ['id' => $l->id, 'amount' => $l->amount, 'remaining' => $l->amount, 'date' => $l->date->format('Y-m-d')];
            } else {
                // LIFO: pembayaran memotong hutang TERBARU dulu (dari akhir daftar),
                // baru ke yang lebih lama — sejajar preview tick di modal Bayar
                $payment = $l->amount;
                for ($i = count($debts) - 1; $i >= 0 && $payment > 0; $i--) {
                    $cut = min($payment, $debts[$i]['remaining']);
                    $debts[$i]['remaining'] -= $cut;
                    $payment -= $cut;
                }
                if ($payment > 0) $deposit += $payment;
            }
        }
        $activeDebts = array_values(array_filter($debts, fn ($d) => $d['remaining'] > 0));
        $totalSisa = (int) array_sum(array_column($activeDebts, 'remaining'));
        return compact('activeDebts', 'totalSisa', 'deposit');
    }

    /** Riwayat lengkap satu pelanggan (untuk panel detail) dengan saldo berjalan. */
    public function hpDetailEntries(string $name, ?string $sheetDate = null)
    {
        $query = CustomerLedger::where('name', $name);
        if ($sheetDate !== null) {
            $query->where(fn ($q) => $q->where('sheet_date', $sheetDate)->orWhereNull('sheet_date'));
        }
        $entries = $query->orderBy('date')->orderBy('id')->get();
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
