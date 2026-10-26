<?php

namespace Tests\Unit;

use App\Http\Controllers\PosController;
use PHPUnit\Framework\TestCase;

/**
 * Mengunci aturan pembulatan total transaksi: sisa ribuan < 500 ke bawah,
 * >= 500 ke atas. Aturan yang sama dipakai hitungUlangTotal() di
 * resources/js/pos/transaksi.js.
 */
class PembulatanTotalTest extends TestCase
{
    public function test_bulatkan_ke_ribuan_terdekat(): void
    {
        $kasus = [
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
        foreach ($kasus as $masukan => $diharapkan) {
            $this->assertSame($diharapkan, PosController::bulatkanTotal($masukan), "bulatkanTotal({$masukan})");
        }
    }
}
