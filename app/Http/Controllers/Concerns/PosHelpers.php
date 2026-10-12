<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CustomerLedger;
use Illuminate\Http\Request;

/**
 * Helper kecil bersama seluruh controller POS:
 * - tanggal aktif & parameter URL (semua tab memakai satu tanggal tunggal),
 * - pembulatan total, parsing angka desimal (koma), dan redirect balik ke POS.
 * Logika lembar hasil (salin-tempel antar tanggal) ada di App\Services\HasilSheetService.
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
        $start = $activeMonth.'-01';
        $end = \DateTime::createFromFormat('Y-m-d', $start)->modify('last day of this month')->format('Y-m-d');

        return [$start, $end];
    }

    private function monthLabel(string $activeMonth): string
    {
        $d = \DateTime::createFromFormat('Y-m', $this->normalizeMonth($activeMonth));
        $names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $names[(int) $d->format('n') - 1].' '.$d->format('Y');
    }

    private function makeQueryParams(Request $request, array $overrides = []): array
    {
        $params = array_merge($request->query(), $overrides);

        return array_filter($params, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Redirect kembali ke POS dengan parameter (tab, tx_date, dst.) digabung
     * ke query string aktif; $pesan = flash sukses sekali tampil.
     */
    protected function redirectToPos(Request $request, array $params = [], ?string $pesan = null)
    {
        $redirect = redirect()->route('pos', $this->makeQueryParams($request, $params));

        return $pesan !== null ? $redirect->with('success', $pesan) : $redirect;
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
        return CustomerLedger::forSheet($sheetDate);
    }

    /**
     * Pembulatan total ke ribuan terdekat: sisa < 500 -> ke bawah, >= 500 -> ke atas
     * (499 -> 0, 500 -> 1000). Aturan yang sama ditulis ulang di JS recalcTotals()
     * (_tab_transaksi) — bila aturnya berubah, ubah dua tempat itu.
     */
    public static function roundTotal(int $total): int
    {
        $thousands = intdiv($total, 1000) * 1000;
        $remainder = $total - $thousands;

        return $remainder < 500 ? $thousands : $thousands + 1000;
    }

    /**
     * Angka desimal dari input user: UI memakai koma desimal ("1,5") — (float) "1,5" di PHP = 1,
     * jadi normalisasi dulu ke titik supaya setengah kg ikut tercatat & dihitung.
     * Dipakai untuk qty (kg) maupun nominal uang (edit hutang pribadi).
     */
    public static function parseDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
