<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CustomerLedger;
use App\Models\HasilSheet;
use App\Models\OilStock;
use App\Models\PersonalLedger;
use App\Models\SaldoDeduction;
use App\Models\StockManagement;
use App\Models\StockRemaining;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Logika bersama seluruh controller POS:
 * - tanggal aktif & parameter URL (semua tab memakai satu tanggal tunggal),
 * - lembar hasil (salin-tempel sekali per tanggal, lalu independen),
 * - pembulatan total & parsing qty (koma desimal).
 */
trait PosHelpers
{
    private function normalizeMonth(?string $month): string
    {
        $month = (string) $month;

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : now()->format('Y-m');
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);

        return $d !== false && $d->format('Y-m-d') === $date;
    }

    private function monthRange(string $activeMonth): array
    {
        $activeMonth = $this->normalizeMonth($activeMonth);
        $start = $activeMonth . '-01';
        $end = \DateTime::createFromFormat('Y-m-d', $start)->modify('last day of this month')->format('Y-m-d');
        return [$start, $end];
    }

    private function monthLabel(string $activeMonth): string
    {
        $d = \DateTime::createFromFormat('Y-m', $this->normalizeMonth($activeMonth));
        $names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $names[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
    }

    private function makeQueryParams(Request $request, array $overrides = []): array
    {
        $params = array_merge($request->query(), $overrides);
        return array_filter($params, fn ($v) => $v !== null && $v !== '');
    }

    /** Tanggal aktif dari query/input tx_date; default hari ini. */
    private function activeDate(Request $request): string
    {
        foreach ([$request->query('tx_date'), $request->input('tx_date')] as $date) {
            if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $this->isValidDate($date)) {
                return $date;
            }
        }
        return now()->toDateString();
    }

    /** Entri hutang pelanggan milik lembar hasil tanggal aktif (entri legacy tanpa lembar ikut tampil). */
    private function scopedCustomerLedgers(string $sheetDate)
    {
        return CustomerLedger::where(fn ($q) => $q->where('sheet_date', $sheetDate)->orWhereNull('sheet_date'));
    }

    /**
     * Siapkan lembar hasil untuk tanggal aktif — proses sekali per tanggal.
     * Jika tanggal target masih kosong, seluruh isi tab hasil disalin (copy-paste)
     * dari tanggal sumber; setelah itu kedua tanggal independen — edit satu tidak
     * mempengaruhi yang lain.
     */
    private function ensureHasilSheet(string $txDate): void
    {
        if (HasilSheet::where('date', $txDate)->exists()) {
            return;
        }
        if ($this->hasilHasData($txDate)) {
            HasilSheet::create(['date' => $txDate, 'source_date' => null]);
            return;
        }
        $source = $this->findHasilSource($txDate);
        if ($source === null) {
            return; // belum ada sumber — diproses lagi pada kunjungan berikutnya
        }
        DB::transaction(function () use ($source, $txDate) {
            $this->cloneHasilSheet($source, $txDate);
            HasilSheet::create(['date' => $txDate, 'source_date' => $source]);
        });
    }

    /** Apakah tanggal itu sudah punya isi tab hasil? */
    private function hasilHasData(string $date): bool
    {
        return OilStock::where('date', $date)->exists()
            || StockManagement::where('date', $date)->exists()
            || StockRemaining::where('date', $date)->exists()
            || PersonalLedger::where('date', $date)->exists()
            || SaldoDeduction::where('date', $date)->exists()
            || CustomerLedger::where('sheet_date', $date)->exists();
    }

    /** Sumber salinan: tanggal terakhir yang dilihat, atau tanggal terdekat yang punya data. */
    private function findHasilSource(string $txDate): ?string
    {
        $last = session('hasil_last_date');
        if (is_string($last) && $last !== $txDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $last) && $this->hasilHasData($last)) {
            return $last;
        }

        $before = null;
        $after = null;
        foreach ($this->hasilDateBounds($txDate) as $bound) {
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
    private function hasilDateBounds(string $txDate): array
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
    private function cloneHasilSheet(string $source, string $target): void
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

    public static function roundTotal(int $total): int
    {
        $thousands = intdiv($total, 1000) * 1000;
        $remainder = $total - $thousands;
        return $remainder < 500 ? $thousands : $thousands + 1000;
    }

    /**
     * Qty desimal dari form: UI memakai koma desimal ("1,5") — (float) "1,5" di PHP = 1,
     * jadi normalisasi dulu ke titik supaya setengah kg ikut tercatat & dihitung.
     */
    public static function parseQty(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(',', '.', trim((string) $value));
        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
