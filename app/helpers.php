<?php

if (! function_exists('rupiah')) {
    /**
     * Format angka menjadi format Rupiah, mis. 12500 -> "Rp12.500".
     */
    function rupiah(int|float|null $amount): string
    {
        return 'Rp'.number_format((float) ($amount ?? 0), 0, ',', '.');
    }
}

if (! function_exists('fmtKg')) {
    /**
     * Format kilogram: 1 -> "1", 1.5 -> "1,5".
     */
    function fmtKg(int|float|null $qty): string
    {
        $qty = (float) ($qty ?? 0);
        if ($qty == (int) $qty) {
            return (string) (int) $qty;
        }

        return str_replace('.', ',', rtrim(sprintf('%.1f', $qty), '0'));
    }
}
