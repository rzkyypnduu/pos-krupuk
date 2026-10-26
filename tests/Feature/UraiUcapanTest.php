<?php

namespace Tests\Feature;

use App\Models\BukuPelanggan;
use App\Models\CatatanInput;
use App\Models\ItemPenjualan;
use App\Models\Penjualan;
use App\Models\Produk;
use Tests\TestCase;

class UraiUcapanTest extends TestCase
{
    private function seedProducts(): void
    {
        Produk::firstOrCreate(['nama' => 'Kuning'], ['harga' => 10000]);
        Produk::firstOrCreate(['nama' => 'Lempeng'], ['harga' => 12000]);
    }

    protected function tearDown(): void
    {
        $idPenjualan = Penjualan::where('nama', 'Tester Speech')->pluck('id')->all();
        ItemPenjualan::whereIn('penjualan_id', $idPenjualan)->delete();
        BukuPelanggan::whereIn('penjualan_id', $idPenjualan)
            ->orWhere('nama', 'Tester Speech')->delete();
        Penjualan::whereIn('id', $idPenjualan)->delete();
        CatatanInput::whereIn('penjualan_id', $idPenjualan)
            ->orWhere('teks_mentah', 'like', '%Tester Speech%')->delete();
        Produk::whereIn('nama', ['Kuning', 'Lempeng', 'Gelung'])->delete();
        parent::tearDown();
    }

    public function test_parse_endpoint_extracts_name_and_products(): void
    {
        $this->seedProducts();

        $respons = $this->postJson('/pos/speech/parse', [
            'text' => 'Tester Speech dua kilo kuning',
        ]);

        $respons->assertStatus(200);
        $respons->assertJsonPath('ok', true);
        $respons->assertJsonPath('name', 'Tester Speech');
        $respons->assertJsonPath('items.0.name', 'Kuning');
        $this->assertEqualsWithDelta(2.0, $respons->json('items.0.qty'), 0.001);
        $respons->assertJsonPath('total', 20000);
    }

    public function test_parse_endpoint_requires_text(): void
    {
        $this->postJson('/pos/speech/parse', [])->assertStatus(422);
    }

    public function test_speech_transaction_is_logged_with_match_result(): void
    {
        $this->seedProducts();
        $kuning = Produk::where('nama', 'Kuning')->first();

        $hasilUrai = $this->postJson('/pos/speech/parse', [
            'text' => 'Tester Speech dua kilo kuning',
        ])->json();

        $this->post('/pos/transaksi', [
            'txName' => 'Tester Speech',
            'txDate' => now()->toDateString(),
            'qty' => [$kuning->id => 2],
            'tx_paid' => '',
            'tx_paid_touched' => 0,
            'input_method' => 'speech',
            'input_text' => 'Tester Speech dua kilo kuning',
            'input_parsed' => json_encode($hasilUrai),
            'input_duration_ms' => 4200,
            'input_started_at' => now()->subSeconds(5)->toIso8601String(),
        ])->assertRedirect();

        $catatanInput = CatatanInput::where('teks_mentah', 'Tester Speech dua kilo kuning')->latest('id')->first();

        $this->assertNotNull($catatanInput, 'Log input tidak tersimpan.');
        $this->assertSame('speech', $catatanInput->metode);
        $this->assertSame('ok', $catatanInput->status);
        $this->assertSame('Tester Speech', $catatanInput->json_akhir['name']);
        $this->assertSame(4200, $catatanInput->durasi_ms);
        $this->assertNotNull($catatanInput->penjualan_id);
        $this->assertTrue($catatanInput->json_cocok['name_match']);
        $this->assertTrue($catatanInput->json_cocok['overall']);
        $this->assertEqualsWithDelta(1.0, $catatanInput->json_cocok['item_accuracy'], 0.001);
    }

    public function test_manual_transaction_is_logged_as_manual_method(): void
    {
        $this->seedProducts();
        $kuning = Produk::where('nama', 'Kuning')->first();

        $this->post('/pos/transaksi', [
            'txName' => 'Tester Speech',
            'txDate' => now()->toDateString(),
            'qty' => [$kuning->id => 1.5],
            'tx_paid' => '',
            'tx_paid_touched' => 0,
            'input_method' => 'manual',
            'input_duration_ms' => 9000,
        ])->assertRedirect();

        $catatanInput = CatatanInput::where('metode', 'manual')->latest('id')->first();

        $this->assertNotNull($catatanInput);
        $this->assertSame('manual', $catatanInput->metode);
        $this->assertNull($catatanInput->json_urai);
        $this->assertNull($catatanInput->json_cocok);
        $this->assertSame(9000, $catatanInput->durasi_ms);
        $this->assertSame('Tester Speech', $catatanInput->json_akhir['name']);
    }

    public function test_corrections_are_detected_as_mismatch(): void
    {
        $this->seedProducts();
        $kuning = Produk::where('nama', 'Kuning')->first();

        $hasilUrai = $this->postJson('/pos/speech/parse', [
            'text' => 'Tester Speech dua kilo kuning',
        ])->json();

        $this->post('/pos/transaksi', [
            'txName' => 'Tester Speech',
            'txDate' => now()->toDateString(),
            'qty' => [$kuning->id => 3],
            'tx_paid' => '',
            'tx_paid_touched' => 0,
            'input_method' => 'speech',
            'input_parsed' => json_encode($hasilUrai),
        ])->assertRedirect();

        $catatanInput = CatatanInput::where('metode', 'speech')->latest('id')->first();

        $this->assertNotNull($catatanInput);
        $this->assertFalse($catatanInput->json_cocok['overall']);
        $this->assertEqualsWithDelta(0.0, $catatanInput->json_cocok['item_accuracy'], 0.001);
    }

    /**
     * Middleware ConvertEmptyStringsToNull mengubah tx_note kosong menjadi null.
     * Nilai null harus tetap dapat dicatat tanpa TypeError.
     */
    public function test_empty_note_does_not_crash_logging(): void
    {
        $this->seedProducts();
        $kuning = Produk::where('nama', 'Kuning')->first();

        $this->post('/pos/transaksi', [
            'txName' => 'Tester Speech',
            'txDate' => now()->toDateString(),
            'qty' => [$kuning->id => 1],
            'tx_note' => '',
            'tx_paid' => '',
            'tx_paid_touched' => 0,
            'input_method' => 'manual',
        ])->assertRedirect();

        $catatanInput = CatatanInput::where('metode', 'manual')->latest('id')->first();

        $this->assertNotNull($catatanInput);
        $this->assertSame('ok', $catatanInput->status);
        $this->assertNull($catatanInput->json_akhir['note']);
    }

    public function test_note_absent_from_request_is_logged_as_empty(): void
    {
        $this->seedProducts();
        $kuning = Produk::where('nama', 'Kuning')->first();

        $this->post('/pos/transaksi', [
            'txName' => 'Tester Speech',
            'txDate' => now()->toDateString(),
            'qty' => [$kuning->id => 1],
            'input_method' => 'manual',
        ])->assertRedirect();

        $catatanInput = CatatanInput::where('metode', 'manual')->latest('id')->first();

        $this->assertNotNull($catatanInput);
        $this->assertSame('ok', $catatanInput->status);
    }

    public function test_loose_match_handles_noisy_transcription(): void
    {
        $this->seedProducts();
        Produk::firstOrCreate(['nama' => 'Gelung'], ['harga' => 9000]);

        $respons = $this->postJson('/pos/speech/parse', [
            'text' => 'Budi dua kilo belum',
        ]);

        $respons->assertStatus(200);
        $respons->assertJsonPath('items.0.name', 'Gelung');
        $this->assertEqualsWithDelta(2.0, $respons->json('items.0.qty'), 0.001);
        $this->assertSame('loose', $respons->json('items.0.confidence'));
    }

    public function test_repeated_product_mention_is_overwritten_not_summed(): void
    {
        $this->seedProducts();

        $respons = $this->postJson('/pos/speech/parse', [
            'text' => 'Budi dua kuning tiga kuning',
        ]);

        $respons->assertStatus(200);
        $this->assertCount(1, $respons->json('items'));
        $this->assertEqualsWithDelta(3.0, $respons->json('items.0.qty'), 0.001);
        $this->assertSame(30000, $respons->json('total'));
    }

    public function test_best_of_alternatives_is_chosen(): void
    {
        $this->seedProducts();

        $respons = $this->postJson('/pos/speech/parse', [
            'text' => 'Budi dua kilo',
            'alternatives' => ['Budi dua kilo kuning', 'Budi dua lempeng'],
        ]);

        $respons->assertStatus(200);
        $respons->assertJsonPath('candidates', 3);
        $respons->assertJsonPath('items.0.name', 'Kuning');
    }
}
