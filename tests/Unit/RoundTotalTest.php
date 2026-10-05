<?php

namespace Tests\Unit;

use App\Http\Controllers\PosController;
use PHPUnit\Framework\TestCase;

/**
 * Kunci aturan pembulatan total transaksi: sisa ribuan < 500 ke bawah,
 * >= 500 ke atas. Aturan ini juga ditulis ulang di JS recalcTotals()
 * (_tab_transaksi.blade.php) — lihat docblock PosHelpers::roundTotal.
 */
class RoundTotalTest extends TestCase
{
    public function test_bulatkan_ke_ribuan_terdekat(): void
    {
        $cases = [
            0 => 0,
            499 => 0,
            500 => 1000,
            999 => 1000,
            1000 => 1000,
            1001 => 1000,
            1499 => 1000,
            1500 => 2000,
            12345 => 12000,
            12499 => 12000,
            12500 => 13000,
            123456 => 123000,
            123500 => 124000,
        ];
        foreach ($cases as $input => $expected) {
            $this->assertSame($expected, PosController::roundTotal($input), "roundTotal({$input})");
        }
    }
}
