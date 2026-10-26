<?php

namespace Tests\Feature;

use App\Models\ManajemenStok;
use Tests\TestCase;

class ManajemenStokTest extends TestCase
{
    public function test_edit_harga_berlaku_untuk_semua_kelompok(): void
    {
        $tanggal = now()->toDateString();
        $baris = ManajemenStok::create([
            'tanggal' => $tanggal,
            'nama' => 'Gudang Uji',
            'jumlah' => 30,
            'harga' => 10000,
            'kelompok' => [
                ['id' => 'kelompok-a', 'date' => $tanggal, 'price' => 10000, 'sacks' => [10, 5]],
                ['id' => 'kelompok-b', 'date' => $tanggal, 'price' => 12000, 'sacks' => [15]],
            ],
        ]);

        $response = $this->postJson(route('pos.updateStockMgmtPrice', $baris->id), [
            'price' => '15.000',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('price', 15000)
            ->assertJsonPath('subtotal', 450000);

        $baris->refresh();
        $this->assertSame(15000, (int) $baris->harga);
        $this->assertSame([15000, 15000], array_map(fn ($bagian) => (int) $bagian['price'], $baris->kelompok));
        $this->assertSame(450000.0, $baris->subtotal());
    }
}
