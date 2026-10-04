<?php

namespace Tests\Feature;

use App\Models\CustomerLedger;
use App\Models\InputLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Tests\TestCase;

class SpeechParseTest extends TestCase
{
    private function seedProducts(): void
    {
        Product::firstOrCreate(['name' => 'Kuning'], ['price' => 10000]);
        Product::firstOrCreate(['name' => 'Lempeng'], ['price' => 12000]);
    }

    protected function tearDown(): void
    {
        $saleIds = Sale::where('name', 'Tester Speech')->pluck('id')->all();
        SaleItem::whereIn('sale_id', $saleIds)->delete();
        CustomerLedger::whereIn('sale_id', $saleIds)
            ->orWhere('name', 'Tester Speech')->delete();
        Sale::whereIn('id', $saleIds)->delete();
        InputLog::whereIn('sale_id', $saleIds)
            ->orWhere('raw_text', 'like', '%Tester Speech%')->delete();
        Product::whereIn('name', ['Kuning', 'Lempeng', 'Gelung'])->delete();
        parent::tearDown();
    }

    public function test_parse_endpoint_extracts_name_and_products(): void
    {
        $this->seedProducts();

        $response = $this->postJson('/pos/speech/parse', [
            'text' => 'Tester Speech dua kilo kuning',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('name', 'Tester Speech');
        $response->assertJsonPath('items.0.name', 'Kuning');
        $this->assertEqualsWithDelta(2.0, $response->json('items.0.qty'), 0.001);
        $response->assertJsonPath('total', 20000);
    }

    public function test_parse_endpoint_requires_text(): void
    {
        $this->postJson('/pos/speech/parse', [])->assertStatus(422);
    }

    public function test_speech_transaction_is_logged_with_match_result(): void
    {
        $this->seedProducts();
        $kuning = Product::where('name', 'Kuning')->first();

        $parsed = $this->postJson('/pos/speech/parse', [
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
            'input_parsed' => json_encode($parsed),
            'input_duration_ms' => 4200,
            'input_started_at' => now()->subSeconds(5)->toIso8601String(),
        ])->assertRedirect();

        $log = InputLog::where('raw_text', 'Tester Speech dua kilo kuning')->latest('id')->first();

        $this->assertNotNull($log, 'Log input tidak tersimpan.');
        $this->assertSame('speech', $log->method);
        $this->assertSame('ok', $log->status);
        $this->assertSame('Tester Speech', $log->final_json['name']);
        $this->assertSame(4200, $log->duration_ms);
        $this->assertNotNull($log->sale_id);
        $this->assertTrue($log->match_json['name_match']);
        $this->assertTrue($log->match_json['overall']);
        $this->assertEqualsWithDelta(1.0, $log->match_json['item_accuracy'], 0.001);
    }

    public function test_manual_transaction_is_logged_as_manual_method(): void
    {
        $this->seedProducts();
        $kuning = Product::where('name', 'Kuning')->first();

        $this->post('/pos/transaksi', [
            'txName' => 'Tester Speech',
            'txDate' => now()->toDateString(),
            'qty' => [$kuning->id => 1.5],
            'tx_paid' => '',
            'tx_paid_touched' => 0,
            'input_method' => 'manual',
            'input_duration_ms' => 9000,
        ])->assertRedirect();

        $log = InputLog::where('method', 'manual')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('manual', $log->method);
        $this->assertNull($log->parsed_json);
        $this->assertNull($log->match_json);
        $this->assertSame(9000, $log->duration_ms);
        $this->assertSame('Tester Speech', $log->final_json['name']);
    }

    public function test_corrections_are_detected_as_mismatch(): void
    {
        $this->seedProducts();
        $kuning = Product::where('name', 'Kuning')->first();

        $parsed = $this->postJson('/pos/speech/parse', [
            'text' => 'Tester Speech dua kilo kuning',
        ])->json();

        $this->post('/pos/transaksi', [
            'txName' => 'Tester Speech',
            'txDate' => now()->toDateString(),
            'qty' => [$kuning->id => 3],
            'tx_paid' => '',
            'tx_paid_touched' => 0,
            'input_method' => 'speech',
            'input_parsed' => json_encode($parsed),
        ])->assertRedirect();

        $log = InputLog::where('method', 'speech')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertFalse($log->match_json['overall']);
        $this->assertEqualsWithDelta(0.0, $log->match_json['item_accuracy'], 0.001);
    }

    /**
     * Regresi: middleware ConvertEmptyStringsToNull membuat tx_note = null,
     * sebelumnya melempar TypeError di logInput().
     */
    public function test_empty_note_does_not_crash_logging(): void
    {
        $this->seedProducts();
        $kuning = Product::where('name', 'Kuning')->first();

        $this->post('/pos/transaksi', [
            'txName' => 'Tester Speech',
            'txDate' => now()->toDateString(),
            'qty' => [$kuning->id => 1],
            'tx_note' => '',
            'tx_paid' => '',
            'tx_paid_touched' => 0,
            'input_method' => 'manual',
        ])->assertRedirect();

        $log = InputLog::where('method', 'manual')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('ok', $log->status);
        $this->assertNull($log->final_json['note']);
    }

    public function test_note_absent_from_request_is_logged_as_empty(): void
    {
        $this->seedProducts();
        $kuning = Product::where('name', 'Kuning')->first();

        $this->post('/pos/transaksi', [
            'txName' => 'Tester Speech',
            'txDate' => now()->toDateString(),
            'qty' => [$kuning->id => 1],
            'input_method' => 'manual',
        ])->assertRedirect();

        $log = InputLog::where('method', 'manual')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('ok', $log->status);
    }

    public function test_loose_match_handles_noisy_transcription(): void
    {
        $this->seedProducts();
        Product::firstOrCreate(['name' => 'Gelung'], ['price' => 9000]);

        $response = $this->postJson('/pos/speech/parse', [
            'text' => 'Budi dua kilo belum',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('items.0.name', 'Gelung');
        $this->assertEqualsWithDelta(2.0, $response->json('items.0.qty'), 0.001);
        $this->assertSame('loose', $response->json('items.0.confidence'));
    }

    public function test_repeated_product_mention_is_overwritten_not_summed(): void
    {
        $this->seedProducts();

        $response = $this->postJson('/pos/speech/parse', [
            'text' => 'Budi dua kuning tiga kuning',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('items'));
        $this->assertEqualsWithDelta(3.0, $response->json('items.0.qty'), 0.001);
        $this->assertSame(30000, $response->json('total'));
    }

    public function test_best_of_alternatives_is_chosen(): void
    {
        $this->seedProducts();

        $response = $this->postJson('/pos/speech/parse', [
            'text' => 'Budi dua kilo',
            'alternatives' => ['Budi dua kilo kuning', 'Budi dua lempeng'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('candidates', 3);
        $response->assertJsonPath('items.0.name', 'Kuning');
    }
}
