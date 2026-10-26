<?php

namespace Tests\Feature;

use App\Models\PotonganSaldo;
use Tests\TestCase;

class SaldoTest extends TestCase
{
    public function test_input_rupiah_berformat_tersimpan_sebagai_angka_penuh(): void
    {
        $tanggal = now()->toDateString();

        $this->post(route('pos.simpanSaldo'), [
            'saldoA' => '15.000',
            'saldoB' => '5.000',
            'tx_date' => $tanggal,
        ])->assertSessionHasNoErrors();

        $saldo = PotonganSaldo::where('tanggal', $tanggal)->first();
        $this->assertNotNull($saldo);
        $this->assertSame(15000, (int) $saldo->nilai_a);
        $this->assertSame(5000, (int) $saldo->nilai_b);
        $this->assertSame(10000, $saldo->hasil());

        $this->get('/pos?tab=hasil&tx_date='.$tanggal)
            ->assertSee('Rp10.000');
    }

    public function test_input_bukan_angka_tetap_ditolak(): void
    {
        $this->post(route('pos.simpanSaldo'), [
            'saldoA' => 'bukan-angka',
            'saldoB' => '5.000',
            'tx_date' => now()->toDateString(),
        ])->assertSessionHasErrors('saldoA');
    }
}
