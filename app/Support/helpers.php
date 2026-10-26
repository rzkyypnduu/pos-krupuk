<?php

if (! function_exists('rupiah')) {
    /**
     * Format angka menjadi Rupiah, mis. 12500 -> "Rp12.500".
     * resources/js/pos/format.js menyediakan tampilkanRupiahJs dengan hasil yang sama.
     */
    function rupiah(int|float|null $jumlah): string
    {
        return 'Rp'.number_format((float) ($jumlah ?? 0), 0, ',', '.');
    }
}

if (! function_exists('uraiRupiah')) {
    /**
     * Ambil nilai rupiah bulat dari input user: "1.500"/"1,500" -> 1500,
     * null/kosong -> 0. Dipakai form transaksi dan modal pembayaran;
     * resources/js/pos/format.js menyediakan uraiUang untuk pemisah ribuan.
     */
    function uraiRupiah(mixed $nilai): int
    {
        if ($nilai === null || $nilai === '') {
            return 0;
        }

        return (int) str_replace(',', '.', str_replace('.', '', (string) $nilai));
    }
}

if (! function_exists('bulatkanTotal')) {
    /**
     * Bulatkan total transaksi ke ribuan terdekat: sisa < 500 ke bawah,
     * >= 500 ke atas (499 -> 0, 500 -> 1000). Aturan yang sama dipakai
     * hitungUlangTotal() di resources/js/pos/transaksi.js.
     */
    function bulatkanTotal(int $total): int
    {
        $ribuan = intdiv($total, 1000) * 1000;
        $sisa = $total - $ribuan;

        return $sisa < 500 ? $ribuan : $ribuan + 1000;
    }
}

if (! function_exists('tampilkanKilogram')) {
    /**
     * Format kilogram (2 desimal, tanpa nol menggantung): 1 -> "1", 1.5 -> "1,5", 2.25 -> "2,25".
     * resources/js/pos/format.js menyediakan tampilkanKilogramJs dengan hasil yang sama.
     */
    function tampilkanKilogram(int|float|null $jumlah): string
    {
        $jumlah = (float) ($jumlah ?? 0);
        if ($jumlah == (int) $jumlah) {
            return (string) (int) $jumlah;
        }

        return str_replace('.', ',', rtrim(rtrim(sprintf('%.2f', $jumlah), '0'), '.'));
    }
}
