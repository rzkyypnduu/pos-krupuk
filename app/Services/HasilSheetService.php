<?php

namespace App\Services;

use App\Models\CustomerLedger;
use App\Models\HasilSheet;
use App\Models\OilStock;
use App\Models\PersonalLedger;
use App\Models\SaldoDeduction;
use App\Models\StockManagement;
use App\Models\StockRemaining;
use Illuminate\Support\Facades\DB;

/**
 * Lembar hasil (tab Hasil) = lembar harian per tanggal.
 * Saat pertama kali membuka tanggal yang masih kosong, isi tab hasil disalin
 * (copy-paste) dari tanggal sumber; setelah itu kedua tanggal independen —
 * edit satu tidak mempengaruhi yang lain.
 */
class HasilSheetService
{
    /**
     * Siapkan lembar hasil untuk tanggal aktif — proses sekali per tanggal.
     * Jika tanggal target masih kosong, seluruh isi tab hasil disalin dari
     * tanggal sumber; setelah itu kedua tanggal independen.
     */
    public function ensure(string $txDate): void
    {
        if (HasilSheet::where('date', $txDate)->exists()) {
            return;
        }
        if ($this->hasData($txDate)) {
            HasilSheet::create(['date' => $txDate, 'source_date' => null]);

            return;
        }
        $source = $this->findSource($txDate);
        if ($source === null) {
            return; // belum ada sumber — diproses lagi pada kunjungan berikutnya
        }
        DB::transaction(function () use ($source, $txDate) {
            $this->clone($source, $txDate);
            HasilSheet::create(['date' => $txDate, 'source_date' => $source]);
        });
    }

    /** Apakah tanggal itu sudah punya isi tab hasil? */
    public function hasData(string $date): bool
    {
        return OilStock::where('date', $date)->exists()
            || StockManagement::where('date', $date)->exists()
            || StockRemaining::where('date', $date)->exists()
            || PersonalLedger::where('date', $date)->exists()
            || SaldoDeduction::where('date', $date)->exists()
            || CustomerLedger::where('sheet_date', $date)->exists();
    }

    /** Sumber salinan: tanggal terakhir yang dilihat, atau tanggal terdekat yang punya data. */
    private function findSource(string $txDate): ?string
    {
        $last = session('hasil_last_date');
        if (is_string($last) && $last !== $txDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $last) && $this->hasData($last)) {
            return $last;
        }

        $before = null;
        $after = null;
        foreach ($this->dateBounds($txDate) as $bound) {
            if ($bound['before'] !== null && ($before === null || $bound['before'] > $before)) {
                $before = $bound['before'];
            }
            if ($bound['after'] !== null && ($after === null || $bound['after'] < $after)) {
                $after = $bound['after'];
            }
        }

        return $before ?? $after;
    }

    /** Batas tanggal yang punya data per tabel (kolom date; pelanggan memakai sheet_date). */
    private function dateBounds(string $txDate): array
    {
        $bounds = [];
        foreach ([OilStock::class, StockManagement::class, StockRemaining::class, PersonalLedger::class, SaldoDeduction::class] as $model) {
            $bounds[] = [
                'before' => $model::where('date', '<', $txDate)->max('date'),
                'after' => $model::where('date', '>', $txDate)->min('date'),
            ];
        }
        $bounds[] = [
            'before' => CustomerLedger::whereNotNull('sheet_date')->where('sheet_date', '<', $txDate)->max('sheet_date'),
            'after' => CustomerLedger::whereNotNull('sheet_date')->where('sheet_date', '>', $txDate)->min('sheet_date'),
        ];

        return $bounds;
    }

    /** Salin seluruh isi tab hasil dari tanggal sumber ke tanggal target (independen). */
    private function clone(string $source, string $target): void
    {
        $models = [OilStock::class, StockManagement::class, StockRemaining::class, PersonalLedger::class, SaldoDeduction::class];
        foreach ($models as $model) {
            foreach ($model::where('date', $source)->get() as $row) {
                $clone = $row->replicate();
                $clone->date = $target;
                $clone->save();
            }
        }
        // Rekap hutang pelanggan: entri disalin dengan lembar baru; tanggal riwayat
        // (date) tetap asli supaya catatan peristiwa tidak ikut bergeser.
        foreach (CustomerLedger::where('sheet_date', $source)->get() as $row) {
            $clone = $row->replicate();
            $clone->sheet_date = $target;
            $clone->save();
        }
    }
}
