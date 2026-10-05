<?php

if (! function_exists('rupiah')) {
    /**
     * Format angka menjadi format Rupiah, mis. 12500 -> "Rp12.500".
     * Pasangan JS-nya rupiahJs() di _tab_hasil — keduanya harus menghasilkan
     * teks yang sama untuk nilai yang sama.
     */
    function rupiah(int|float|null $amount): string
    {
        return 'Rp'.number_format((float) ($amount ?? 0), 0, ',', '.');
    }
}

if (! function_exists('parseRupiah')) {
    /**
     * Ambil nilai rupiah bulat dari input user: "1.500"/"1,500" -> 1500,
     * null/kosong -> 0. Dipakai kolom Dibayar (_tab_transaksi) dan modal Bayar
     * (TransaksiController::bayarModal); pasangan JS-nya updateStatus() di
     * _tab_transaksi dan $store.pay.parse() di pos-pay.js (buang pemisah ribuan).
     */
    function parseRupiah(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) str_replace(',', '.', str_replace('.', '', (string) $value));
    }
}

if (! function_exists('fmtKg')) {
    /**
     * Format kilogram (2 desimal, tanpa nol menggantung): 1 -> "1", 1.5 -> "1,5", 2.25 -> "2,25".
     * Pasangan JS-nya fmtKgJs() di _tab_hasil.blade.php — keduanya harus menghasilkan
     * angka yang sama untuk nilai yang sama.
     */
    function fmtKg(int|float|null $qty): string
    {
        $qty = (float) ($qty ?? 0);
        if ($qty == (int) $qty) {
            return (string) (int) $qty;
        }

        return str_replace('.', ',', rtrim(rtrim(sprintf('%.2f', $qty), '0'), '.'));
    }
}
