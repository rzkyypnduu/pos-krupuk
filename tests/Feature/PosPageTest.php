<?php

namespace Tests\Feature;

use App\Models\CustomerLedger;
use App\Models\InputLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Tests\TestCase;

class PosPageTest extends TestCase
{
    public function test_pos_page_loads(): void
    {
        $response = $this->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('POS Krupuk');
    }

    public function test_transaction_form_is_hidden_by_default(): void
    {
        $response = $this->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('Transaksi Hari Ini');
        $response->assertSee('+ Transaksi Baru');
        $response->assertDontSee('id="tx-form-card"', false);
        $response->assertDontSee('name="txName"', false);
    }

    public function test_transaction_form_opens_with_new_query_param(): void
    {
        $response = $this->get('/pos?new=1');

        $response->assertStatus(200);
        $response->assertSee('id="tx-form-card"', false);
        $response->assertSee('name="txName"', false);
        $response->assertDontSee('+ Transaksi Baru');
    }

    public function test_transaction_form_keeps_month_when_reopening(): void
    {
        $response = $this->get('/pos?bulan=2026-08&new=1');

        $response->assertStatus(200);
        $response->assertSee('id="tx-form-card"', false);
        $response->assertSee('bulan=2026-08', false);
    }

    public function test_today_page_sections_are_stacked_in_order(): void
    {
        $html = $this->get('/pos')->getContent();

        $order = [
            'Transaksi Hari Ini',
            'Pengeluaran Laci',
            'Ringkasan Hari Ini',
            'Ringkasan Bulan',
        ];

        $lastPos = -1;
        foreach ($order as $needle) {
            $pos = strpos($html, $needle);
            $this->assertNotFalse($pos, "Bagian [{$needle}] tidak ditemukan.");
            $this->assertGreaterThan($lastPos, $pos, "Bagian [{$needle}] urutannya salah.");
            $lastPos = $pos;
        }
    }

    public function test_closing_form_returns_to_page_without_form(): void
    {
        $open = $this->get('/pos?new=1');
        $open->assertSee('id="tx-form-card"', false);

        $closed = $this->get('/pos');
        $closed->assertDontSee('id="tx-form-card"', false);
    }

    public function test_successful_save_closes_transaction_form(): void
    {
        $product = Product::firstOrCreate(['name' => 'Kuning Tutup Form'], ['price' => 10000]);
        $customer = 'Tester Tutup Form';

        $response = $this->post('/pos/transaksi?tab=transaksi&bulan=2026-09&tx_date=2026-09-30&new=1', [
            'txName' => $customer,
            'txDate' => '2026-09-30',
            'qty' => [$product->id => 2],
            'tx_paid_touched' => 0,
            'input_method' => 'manual',
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('new=', $location);
        $this->assertStringContainsString('bulan=2026-09', $location);
        $this->assertStringContainsString('tx_date=2026-09-30', $location);

        $sale = Sale::where('name', $customer)->latest('id')->first();
        $this->assertNotNull($sale);

        SaleItem::where('sale_id', $sale->id)->delete();
        CustomerLedger::where('sale_id', $sale->id)->delete();
        InputLog::where('sale_id', $sale->id)->delete();
        Sale::where('id', $sale->id)->delete();
        Product::where('name', 'Kuning Tutup Form')->delete();
    }

    public function test_decimal_qty_with_comma_is_saved_not_truncated(): void
    {
        // form qty & speech mengirim koma desimal ("1,5"); (float) "1,5" di PHP = 1
        $p1 = Product::firstOrCreate(['name' => 'Kuning DesimalA_' . uniqid()], ['price' => 10000]);
        $p2 = Product::firstOrCreate(['name' => 'Kuning DesimalB_' . uniqid()], ['price' => 20000]);
        $customer = 'Tester Desimal_' . uniqid();

        $this->post('/pos/transaksi?tab=transaksi&tx_date=2026-09-30', [
            'txName' => $customer,
            'txDate' => '2026-09-30',
            'qty' => [$p1->id => '1,5', $p2->id => '0,5'],
            'tx_paid_touched' => 0,
            'input_method' => 'manual',
        ])->assertSessionHasNoErrors();

        $sale = Sale::where('name', $customer)->latest('id')->first();
        $this->assertNotNull($sale);

        $qtys = SaleItem::where('sale_id', $sale->id)->pluck('qty', 'product_id');
        $this->assertEqualsWithDelta(1.5, (float) $qtys[$p1->id], 0.001, 'Qty 1,5 harus tersimpan 1,5, bukan 1.');
        $this->assertEqualsWithDelta(0.5, (float) $qtys[$p2->id], 0.001, 'Qty 0,5 harus ikut tersimpan, bukan gugur.');

        // total ikut terhitung desimal: 1,5×10000 + 0,5×20000 = 25000
        $this->assertSame(25000, (int) $sale->raw_total);

        // tampil di tabel transaksi hari ini dengan setengahnya
        $html = $this->get('/pos?tab=transaksi&tx_date=2026-09-30')->getContent();
        $this->assertStringContainsString('1,5 kg', $html);
        $this->assertStringContainsString('0,5 kg', $html);

        SaleItem::where('sale_id', $sale->id)->delete();
        CustomerLedger::where('sale_id', $sale->id)->delete();
        InputLog::where('sale_id', $sale->id)->delete();
        Sale::where('id', $sale->id)->delete();
        Product::whereIn('id', [$p1->id, $p2->id])->delete();
    }

    public function test_failed_save_keeps_transaction_form_open(): void
    {
        $this->get('/pos?new=1');

        $response = $this->post('/pos/transaksi?tab=transaksi&tx_date=2026-09-30&new=1', [
            'txName' => 'Tester Gagal Simpan',
            'txDate' => '2026-09-30',
            'tx_paid_touched' => 0,
        ]);

        $response->assertSessionHasErrors('txQty');
        $this->assertStringContainsString('new=1', (string) $response->headers->get('Location'));
    }

    public function test_speech_panel_has_delete_and_clear_buttons(): void
    {
        $html = $this->get('/pos?new=1')->getContent();

        // Bersihkan berada di dalam kotak transkrip, ujung kanan setelah teksnya
        $this->assertMatchesRegularExpression(
            '/id="txSpeechTranscript"[^>]*>\s*<span[^>]*id="txSpeechTranscriptText"[^>]*><\/span>\s*<button[^>]*id="txSpeechDelete"[^>]*>Bersihkan</s',
            $html
        );
        // nama tombol: Bersihkan = buang teks suara saja, Hapus = teks suara + jumlah
        $this->assertMatchesRegularExpression('/id="txSpeechDelete"[^>]*>Bersihkan</', $html);
        $this->assertMatchesRegularExpression('/id="txSpeechClear"[^>]*>Hapus</', $html);
        // Hapus tetap di baris status, sebelum kotak transkrip
        $this->assertMatchesRegularExpression('/id="txSpeechStatus"[\s\S]{0,300}id="txSpeechClear"/', $html);
        $this->assertLessThan(
            strpos($html, 'id="txSpeechDelete"'),
            strpos($html, 'id="txSpeechTranscript"')
        );
    }

    public function test_speech_script_is_cache_busted(): void
    {
        $html = $this->get('/pos?new=1')->getContent();

        $this->assertMatchesRegularExpression('/js\/pos-speech\.js\?v=\d+/', $html);
    }

    public function test_recap_money_values_are_rows_not_cards(): void
    {
        $html = $this->get('/pos')->getContent();

        // 3 baris (Diterima, Pengeluaran, Kas bersih) x 2 ringkasan (hari & bulan)
        $this->assertSame(6, substr_count($html, 'class="tx-summ-row'));
        $this->assertStringNotContainsString('class="tx-sidebar-stat"', $html);
        $this->assertStringContainsString('Kas bersih', $html);
    }

    public function test_qty_buttons_trigger_recalc_totals(): void
    {
        Product::create(['name' => 'Kabur', 'price' => 10000]);

        $html = $this->get('/pos?new=1')->getContent();

        // input jumlah punya handler yang memanggil recalcTotals
        $this->assertMatchesRegularExpression(
            '/data-product-id="\d+"[^>]*oninput="[^"]*recalcTotals\(\)/',
            $html
        );
        // tombol + / - harus mengirim event input (bukan change) supaya Total kg ikut terhitung
        $inputs = substr_count($html, 'data-product-id="');
        $this->assertGreaterThan(0, $inputs);
        $this->assertSame($inputs * 2, substr_count($html, "dispatchEvent(new Event('input', {bubbles:true}))"));
        $this->assertStringNotContainsString("dispatchEvent(new Event('change'))", $html);
    }
}
