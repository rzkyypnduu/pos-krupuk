<?php

namespace Tests\Feature;

use App\Models\BukuPelanggan;
use App\Models\CatatanInput;
use App\Models\ItemPenjualan;
use App\Models\Penjualan;
use App\Models\Produk;
use Tests\TestCase;

class HalamanPosTest extends TestCase
{
    public function test_pos_page_loads(): void
    {
        $respons = $this->get('/pos');

        $respons->assertStatus(200);
        $respons->assertSee('UD Krupuk Lancar');
    }

    public function test_transaction_form_is_hidden_by_default(): void
    {
        $respons = $this->get('/pos');

        $respons->assertStatus(200);
        $respons->assertSee('Transaksi Hari Ini');
        $respons->assertSee('+ Transaksi Baru');
        $respons->assertDontSee('id="tx-form-card"', false);
        $respons->assertDontSee('name="txName"', false);
    }

    public function test_transaction_form_opens_with_new_query_param(): void
    {
        $respons = $this->get('/pos?new=1');

        $respons->assertStatus(200);
        $respons->assertSee('id="tx-form-card"', false);
        $respons->assertSee('name="txName"', false);
        $respons->assertDontSee('+ Transaksi Baru');
    }

    public function test_transaction_form_keeps_month_when_reopening(): void
    {
        $respons = $this->get('/pos?bulan=2026-08&new=1');

        $respons->assertStatus(200);
        $respons->assertSee('id="tx-form-card"', false);
        $respons->assertSee('bulan=2026-08', false);
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
        $produk = Produk::firstOrCreate(['nama' => 'Kuning Tutup Form'], ['harga' => 10000]);
        $customer = 'Tester Tutup Form';

        $respons = $this->post('/pos/transaksi?tab=transaksi&bulan=2026-09&tx_date=2026-09-30&new=1', [
            'txName' => $customer,
            'txDate' => '2026-09-30',
            'qty' => [$produk->id => 2],
            'tx_paid_touched' => 0,
            'input_method' => 'manual',
        ]);

        $respons->assertRedirect();
        $location = (string) $respons->headers->get('Location');
        $this->assertStringNotContainsString('new=', $location);
        $this->assertStringContainsString('bulan=2026-09', $location);
        $this->assertStringContainsString('tx_date=2026-09-30', $location);

        $penjualan = Penjualan::where('nama', $customer)->latest('id')->first();
        $this->assertNotNull($penjualan);

        ItemPenjualan::where('penjualan_id', $penjualan->id)->delete();
        BukuPelanggan::where('penjualan_id', $penjualan->id)->delete();
        CatatanInput::where('penjualan_id', $penjualan->id)->delete();
        Penjualan::where('id', $penjualan->id)->delete();
        Produk::where('nama', 'Kuning Tutup Form')->delete();
    }

    public function test_decimal_qty_with_comma_is_saved_not_truncated(): void
    {
        // form qty & speech mengirim koma desimal ("1,5"); (float) "1,5" di PHP = 1
        $p1 = Produk::firstOrCreate(['nama' => 'Kuning DesimalA_'.uniqid()], ['harga' => 10000]);
        $p2 = Produk::firstOrCreate(['nama' => 'Kuning DesimalB_'.uniqid()], ['harga' => 20000]);
        $customer = 'Tester Desimal_'.uniqid();

        $this->post('/pos/transaksi?tab=transaksi&tx_date=2026-09-30', [
            'txName' => $customer,
            'txDate' => '2026-09-30',
            'qty' => [$p1->id => '1,5', $p2->id => '0,5'],
            'tx_paid_touched' => 0,
            'input_method' => 'manual',
        ])->assertSessionHasNoErrors();

        $penjualan = Penjualan::where('nama', $customer)->latest('id')->first();
        $this->assertNotNull($penjualan);

        $qtys = ItemPenjualan::where('penjualan_id', $penjualan->id)->pluck('jumlah', 'produk_id');
        $this->assertEqualsWithDelta(1.5, (float) $qtys[$p1->id], 0.001, 'Qty 1,5 harus tersimpan 1,5, bukan 1.');
        $this->assertEqualsWithDelta(0.5, (float) $qtys[$p2->id], 0.001, 'Qty 0,5 harus ikut tersimpan, bukan gugur.');

        // total ikut terhitung desimal: 1,5×10000 + 0,5×20000 = 25000
        $this->assertSame(25000, (int) $penjualan->total_kotor);

        // tampil di tabel transaksi hari ini dengan setengahnya
        $html = $this->get('/pos?tab=transaksi&tx_date=2026-09-30')->getContent();
        $this->assertStringContainsString('1,5 kg', $html);
        $this->assertStringContainsString('0,5 kg', $html);

        ItemPenjualan::where('penjualan_id', $penjualan->id)->delete();
        BukuPelanggan::where('penjualan_id', $penjualan->id)->delete();
        CatatanInput::where('penjualan_id', $penjualan->id)->delete();
        Penjualan::where('id', $penjualan->id)->delete();
        Produk::whereIn('id', [$p1->id, $p2->id])->delete();
    }

    public function test_failed_save_keeps_transaction_form_open(): void
    {
        $this->get('/pos?new=1');

        $respons = $this->post('/pos/transaksi?tab=transaksi&tx_date=2026-09-30&new=1', [
            'txName' => 'Tester Gagal Simpan',
            'txDate' => '2026-09-30',
            'tx_paid_touched' => 0,
        ]);

        $respons->assertSessionHasErrors('txQty');
        $this->assertStringContainsString('new=1', (string) $respons->headers->get('Location'));
    }

    public function test_speech_panel_has_delete_and_clear_buttons(): void
    {
        $html = $this->get('/pos?new=1')->getContent();

        // Bersihkan berada di dalam kotak transkrip, ujung kanan setelah teksnya
        $this->assertMatchesRegularExpression(
            '/id="txSpeechTranscript"[^>]*>\s*<span[^>]*id="txSpeechTranscriptText"[^>]*><\/span>\s*<button[^>]*id="txSpeechDelete"[^>]*>Bersihkan<\/button>/',
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

    public function test_speech_script_is_loaded_via_vite_bundle(): void
    {
        $html = $this->get('/pos?new=1')->getContent();

        // suara.js dimuat sebagai module Vite, bukan file statis.
        // Pola ini berlaku untuk mode dev (hot: .../resources/js/app.js) maupun build (/build/assets/app-*.js).
        $this->assertMatchesRegularExpression('/<script[^>]+type="module"[^>]+src="[^"]*app[^"]*\.js"/', $html);
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
        Produk::create(['nama' => 'Kabur', 'harga' => 10000]);

        $html = $this->get('/pos?new=1')->getContent();

        // Data hitung ada di form; handler inline tidak lagi dipakai.
        $this->assertStringContainsString('data-total-dibulatkan="', $html);
        $this->assertStringContainsString('data-harga-produk="', $html);
        $this->assertStringNotContainsString('oninput="', $html);

        $skrip = (string) file_get_contents(base_path('resources/js/pos/transaksi.js'));
        $this->assertStringContainsString('function hitungUlangTotal()', $skrip);
        $this->assertStringContainsString('window.hitungUlangTotal = hitungUlangTotal', $skrip);

        // Tombol + / - dikirim sebagai data-qty-step (delegasi antarmuka.js dispatch event input).
        $inputs = substr_count($html, 'data-product-id="');
        $this->assertGreaterThan(0, $inputs);
        $this->assertSame($inputs * 2, substr_count($html, 'data-qty-step="'));
        $this->assertSame($inputs * 2, substr_count($html, 'data-delta="'));
        $this->assertStringNotContainsString("dispatchEvent(new Event('change'))", $html);
    }

    public function test_ringkasan_tab_loads(): void
    {
        $response = $this->get('/pos?tab=ringkasan');

        $response->assertStatus(200);
        $response->assertSee('Ringkasan neraca');
        $response->assertSee('Produk terlaris');
        $response->assertSee('Pelanggan teraktif');

        $html = $response->getContent();
        $this->assertStringContainsString('data-grafik="', $html);
        $this->assertStringContainsString('data-label-bulan="', $html);
        $this->assertStringNotContainsString('window.POS_GRAFIK', $html);
        $this->assertStringNotContainsString('window.POS_MONTH_LABEL', $html);
    }

    public function test_empty_debt_cell_becomes_zero_and_columns_follow_largest_debt(): void
    {
        $tanggal = now()->toDateString();
        $nama = 'UjiKolomHutang_'.uniqid();
        $hutangPertama = BukuPelanggan::create([
            'tanggal' => $tanggal, 'tanggal_lembar' => $tanggal, 'nama' => $nama,
            'jumlah' => 1000, 'jenis' => 'tambah',
        ]);
        BukuPelanggan::create([
            'tanggal' => $tanggal, 'tanggal_lembar' => $tanggal, 'nama' => $nama,
            'jumlah' => 2000, 'jenis' => 'tambah',
        ]);
        BukuPelanggan::create([
            'tanggal' => $tanggal, 'tanggal_lembar' => $tanggal, 'nama' => $nama,
            'jumlah' => 3000, 'jenis' => 'tambah',
        ]);

        $html = $this->get('/pos?tab=hasil&tx_date='.$tanggal)->getContent();
        $this->assertMatchesRegularExpression('/class="hp-head num" colspan="3"/', $html);
        $this->assertStringNotContainsString('Klik untuk ubah total', $html);
        $this->assertMatchesRegularExpression(
            '/class="num hp-total-cell">\s*<span class="hp-total-amt">Rp[\d.]+<\/span>\s*<\/td>/',
            $html
        );

        $this->postJson(route('pos.adjustDebtCell'), [
            'debt_id' => $hutangPertama->id,
            'new_remaining' => '',
            'tx_date' => $tanggal,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('buku_pelanggan', [
            'id' => $hutangPertama->id,
            'jumlah' => 0,
        ]);

        $htmlSetelah = $this->get('/pos?tab=hasil&tx_date='.$tanggal)->getContent();
        $this->assertMatchesRegularExpression('/class="hp-head num" colspan="2"/', $htmlSetelah);

        $skrip = (string) file_get_contents(base_path('resources/js/pos/hasil/hutang-pelanggan.js'));
        $this->assertStringContainsString("new_remaining: nilai === '' ? '0' : digitUang(nilai)", $skrip);
        $this->assertStringNotContainsString('adjust-total', $skrip);
        $this->assertStringNotContainsString('new_total', $skrip);
        $this->assertStringContainsString('function syncColumns()', $skrip);
    }
}
