<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Kunci perilaku fmtKg (app/helpers.php) — pasangan fmtKgJs di view.
 * Sebelum fix: fmtKg(1.04) menghasilkan "1," (rtrim memotong nol jadi koma
 * menggantung) dan desimal ke-2 dibuang ("2,25" jadi "2,3").
 */
class FmtKgTest extends TestCase
{
    public function test_bilangan_bulat_tanpa_desimal(): void
    {
        $this->assertSame('0', fmtKg(0));
        $this->assertSame('1', fmtKg(1));
        $this->assertSame('100', fmtKg(100));
        $this->assertSame('100', fmtKg(100.0));
        $this->assertSame('0', fmtKg(null));
    }

    public function test_satu_desimal(): void
    {
        $this->assertSame('1,5', fmtKg(1.5));
        $this->assertSame('0,5', fmtKg(0.5));
        $this->assertSame('10,5', fmtKg(10.5));
        // 10.50 harus jadi "10,5" — nol belakang dibuang, titik tidak menggantung
        $this->assertSame('10,5', fmtKg(10.50));
        $this->assertSame('1,1', fmtKg(1.10));
    }

    public function test_dua_desimal_tidak_dibuang(): void
    {
        $this->assertSame('2,25', fmtKg(2.25));
        $this->assertSame('1,04', fmtKg(1.04));
        $this->assertSame('100,05', fmtKg(100.05));
        $this->assertSame('0,05', fmtKg(0.05));
    }

    public function test_tidak_pernah_menghasilkan_koma_menggantung(): void
    {
        // kasus bug lama: sprintf %.1f("1.04") = "1.0" lalu rtrim("0") = "1." -> "1,"
        foreach ([1.04, 100.05, 2.0, 10.5, 3.33, 0.9] as $v) {
            $out = fmtKg($v);
            $this->assertStringEndsNotWith(',', $out, "fmtKg($v) = \"$out\"");
            $this->assertStringEndsNotWith('.', $out, "fmtKg($v) = \"$out\"");
        }
    }
}
