<?php

namespace App\Http\Controllers\Concerns;

use App\Models\BukuPelanggan;
use Illuminate\Http\Request;

/**
 * Helper kecil bersama seluruh controller POS:
 * - tanggal aktif & parameter URL (semua tab memakai satu tanggal tunggal),
 * - pembulatan total, parsing angka desimal (koma), dan redirect balik ke POS.
 * Logika lembar hasil (salin-tempel antar tanggal) ada di App\Services\ServiceLembarHasil.
 */
trait BantuanPos
{
    private function normalisasiBulan(?string $month): string
    {
        $month = (string) $month;

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : now()->format('Y-m');
    }

    private function tanggalValid(string $tanggal): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $tanggal);

        return $d !== false && $d->format('Y-m-d') === $tanggal;
    }

    private function rentangBulan(string $bulanAktif): array
    {
        $bulanAktif = $this->normalisasiBulan($bulanAktif);
        $start = $bulanAktif.'-01';
        $end = \DateTime::createFromFormat('Y-m-d', $start)->modify('last day of this month')->format('Y-m-d');

        return [$start, $end];
    }

    private function labelBulan(string $bulanAktif): string
    {
        $d = \DateTime::createFromFormat('Y-m', $this->normalisasiBulan($bulanAktif));
        $daftarNama = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $daftarNama[(int) $d->format('n') - 1].' '.$d->format('Y');
    }

    private function buatParameterQuery(Request $request, array $overrides = []): array
    {
        $parameter = array_merge($request->query(), $overrides);

        return array_filter($parameter, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Redirect kembali ke POS dengan parameter (tab, tx_date, dst.) digabung
     * ke query string aktif; $pesan = flash sukses sekali tampil.
     */
    protected function alihkanKePos(Request $request, array $parameter = [], ?string $pesan = null)
    {
        $redirect = redirect()->route('pos', $this->buatParameterQuery($request, $parameter));

        return $pesan !== null ? $redirect->with('success', $pesan) : $redirect;
    }

    /** Tanggal aktif dari query/input tx_date; default hari ini. */
    private function tanggalAktif(Request $request): string
    {
        foreach ([$request->query('tx_date'), $request->input('tx_date')] as $tanggal) {
            if (is_string($tanggal) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) && $this->tanggalValid($tanggal)) {
                return $tanggal;
            }
        }

        return now()->toDateString();
    }

    /** Entri hutang pelanggan pada lembar tanggal aktif; entri lama tanpa lembar tetap tampil. */
    private function bukuPelangganLingkup(string $tanggalLembar)
    {
        return BukuPelanggan::untukLembar($tanggalLembar);
    }

    /**
     * Pembulatan total ke ribuan terdekat. Implementasi ada di
     * bulatkanTotal() (app/Support/helpers.php); JS hitungUlangTotal()
     * (resources/js/pos/transaksi.js) memakai aturan yang sama.
     */
    public static function bulatkanTotal(int $total): int
    {
        return bulatkanTotal($total);
    }

    /**
     * Angka desimal dari input user: UI memakai koma desimal ("1,5"), sedangkan
     * (float) "1,5" di PHP bernilai 1. Normalisasi ke titik membuat setengah kg
     * ikut tercatat dan dihitung. Dipakai untuk qty (kg) dan nominal uang.
     */
    public static function uraiDesimal(mixed $nilai): ?float
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }
        $teksNormal = str_replace(',', '.', trim((string) $nilai));

        return is_numeric($teksNormal) ? (float) $teksNormal : null;
    }
}
