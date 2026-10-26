<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mengunci format kilogram di app/Support/helpers.php.
 * Nilai harus sama dengan tampilkanKilogramJs di resources/js/pos/format.js.
 * Kasus batas: 1.04 -> "1,04", 2.25 -> "2,25", tanpa koma atau titik menggantung.
 */
class FormatKgTest extends TestCase
{
    public function test_bilangan_bulat_tanpa_desimal(): void
    {
        $this->assertSame('0', tampilkanKilogram(0));
        $this->assertSame('1', tampilkanKilogram(1));
        $this->assertSame('100', tampilkanKilogram(100));
        $this->assertSame('100', tampilkanKilogram(100.0));
        $this->assertSame('0', tampilkanKilogram(null));
    }

    public function test_satu_desimal(): void
    {
        $this->assertSame('1,5', tampilkanKilogram(1.5));
        $this->assertSame('0,5', tampilkanKilogram(0.5));
        $this->assertSame('10,5', tampilkanKilogram(10.5));
        // Nol di belakang dibuang; koma dan titik tidak boleh menggantung.
        $this->assertSame('10,5', tampilkanKilogram(10.50));
        $this->assertSame('1,1', tampilkanKilogram(1.10));
    }

    public function test_dua_desimal_tidak_dibuang(): void
    {
        $this->assertSame('2,25', tampilkanKilogram(2.25));
        $this->assertSame('1,04', tampilkanKilogram(1.04));
        $this->assertSame('100,05', tampilkanKilogram(100.05));
        $this->assertSame('0,05', tampilkanKilogram(0.05));
    }

    public function test_tidak_pernah_menghasilkan_koma_menggantung(): void
    {
        // Semua hasil harus berakhir dengan digit, bukan koma atau titik.
        foreach ([1.04, 100.05, 2.0, 10.5, 3.33, 0.9] as $v) {
            $out = tampilkanKilogram($v);
            $this->assertStringEndsNotWith(',', $out, "tampilkanKilogram($v) = \"$out\"");
            $this->assertStringEndsNotWith('.', $out, "tampilkanKilogram($v) = \"$out\"");
        }
    }
}
