<?php

namespace Tests\Feature;

use App\Http\Controllers\PosController;
use App\Models\CustomerLedger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Tests\TestCase;

class PaymentModalTest extends TestCase
{
    private function controller(): PosController
    {
        return new PosController();
    }

    /** Ambil isi `<tr>` tabel transaksi yang berisi sale name tertentu (assert per-baris). */
    private function rowOfSale(string $html, string $name): string
    {
        preg_match_all('/<tr>.*?<\/tr>/s', $html, $rows);
        foreach ($rows[0] as $row) {
            if (str_contains($row, $name)) {
                return $row;
            }
        }
        $this->fail('Baris transaksi untuk "' . $name . '" tidak ditemukan di render.');
    }

    public function test_payment_only_transaction_is_saved_without_products(): void
    {
        $name = 'HutangOnly_' . uniqid();

        $response = $this->post('/pos/transaksi?tab=transaksi&tx_date=2026-09-30', [
            'txName' => $name,
            'txDate' => '2026-09-30',
            'tx_paid_touched' => 1,
            'tx_paid' => '75.000',
        ]);

        $response->assertSessionHasNoErrors();

        $sale = Sale::where('name', $name)->first();
        $this->assertNotNull($sale, 'Transaksi tanpa produk harus tersimpan.');
        $this->assertSame(0, $sale->rounded_total);
        $this->assertSame(75000, $sale->paid);
        $this->assertSame(-75000, $sale->diff);
        $this->assertTrue($sale->is_paid_btn_clicked);

        // transaksi TIDAK menulis customer_ledgers — tabel hutang di tab Hasil
        // hanya berisi catatan manual & riwayat lama
        $entries = CustomerLedger::where('name', $name)->get();
        $this->assertCount(0, $entries);

        $proc = $this->controller()->processCustomerDebts($name);
        $this->assertSame(0, $proc['totalSisa']);
        $this->assertSame(0, $proc['deposit']);
    }

    public function test_payment_only_transaction_requires_paid_amount(): void
    {
        $response = $this->post('/pos/transaksi?tab=transaksi&tx_date=2026-09-30', [
            'txName' => 'TanpaProdukTanpaBayar_' . uniqid(),
            'txDate' => '2026-09-30',
            'tx_paid_touched' => 0,
        ]);

        $response->assertSessionHasErrors('txQty');
    }

    public function test_bayar_modal_with_two_forms_pays_yesterday_and_today(): void
    {
        $name = 'BayarKmr_' . uniqid();
        CustomerLedger::create([
            'date' => '2026-09-29', 'name' => $name, 'amount' => 200000,
            'type' => 'tambah', 'note' => 'Transaksi kemarin',
        ]);
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => $name, 'raw_total' => 150000,
            'rounded_total' => 150000, 'paid' => 0, 'diff' => 150000,
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $name, 'amount' => 150000,
            'type' => 'tambah', 'note' => 'Transaksi', 'sale_id' => $sale->id,
        ]);

        $response = $this->post('/pos/transaksi/' . $sale->id . '/bayar', [
            'pakai_kemarin' => 1,
            'bayar_kemarin' => '200.000',
            'bayar_hari_ini' => '100.000',
        ]);

        $response->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(100000, $sale->paid);
        $this->assertSame(50000, $sale->diff);
        $this->assertSame(200000, $sale->paid_kemarin);
        $this->assertTrue($sale->is_paid_btn_clicked);

        // pembayaran TIDAK menulis ledger: tidak ada entri "bayar" baru
        $this->assertSame(0, CustomerLedger::where('sale_id', $sale->id)
            ->where('type', 'bayar')->count());
        // tabel hutang tidak ikut berkurang oleh pembayaran (entri "tambah" fixture utuh)
        $proc = $this->controller()->processCustomerDebts($name);
        $this->assertSame(350000, $proc['totalSisa']);
        $this->assertSame(0, $proc['deposit']);
    }

    public function test_debts_are_cut_newest_first_lifo(): void
    {
        $name = 'Lifo_' . uniqid();
        CustomerLedger::create([
            'date' => '2026-09-28', 'name' => $name, 'amount' => 100000,
            'type' => 'tambah', 'note' => 'Hutang lama',
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $name, 'amount' => 150000,
            'type' => 'tambah', 'note' => 'Hutang baru',
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $name, 'amount' => 120000,
            'type' => 'bayar', 'note' => 'Bayar',
        ]);

        $proc = $this->controller()->processCustomerDebts($name);

        // LIFO: 120rb dipotong dari hutang BARU (150rb) dulu → sisa 30rb; hutang lama utuh
        $this->assertCount(2, $proc['activeDebts']);
        $this->assertSame('2026-09-28', $proc['activeDebts'][0]['date']);
        $this->assertSame(100000, $proc['activeDebts'][0]['remaining']);
        $this->assertSame('2026-09-30', $proc['activeDebts'][1]['date']);
        $this->assertSame(30000, $proc['activeDebts'][1]['remaining']);
        $this->assertSame(130000, $proc['totalSisa']);
        $this->assertSame(0, $proc['deposit']);
    }

    public function test_bayar_modal_overpay_does_not_write_ledger(): void
    {
        $name = 'LebihBayar_' . uniqid();
        CustomerLedger::create([
            'date' => '2026-09-29', 'name' => $name, 'amount' => 100000,
            'type' => 'tambah', 'note' => 'Transaksi kemarin',
        ]);
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => $name, 'raw_total' => 100000,
            'rounded_total' => 100000, 'paid' => 0, 'diff' => 100000,
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $name, 'amount' => 100000,
            'type' => 'tambah', 'note' => 'Transaksi', 'sale_id' => $sale->id,
        ]);

        $this->post('/pos/transaksi/' . $sale->id . '/bayar', [
            'pakai_kemarin' => 1,
            'bayar_kemarin' => '250.000',
            'bayar_hari_ini' => '0',
        ])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(250000, $sale->paid_kemarin);
        $this->assertSame(0, $sale->paid);

        // pembayaran tidak menyentuh tabel hutang: fixture tetap 200rb, deposit 0
        $proc = $this->controller()->processCustomerDebts($name);
        $this->assertSame(200000, $proc['totalSisa']);
        $this->assertSame(0, $proc['deposit']);
        $this->assertSame(0, CustomerLedger::where('sale_id', $sale->id)
            ->where('type', 'bayar')->count());
    }

    public function test_bayar_modal_accepts_zero(): void
    {
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => 'Kosong_' . uniqid(),
            'raw_total' => 50000, 'rounded_total' => 50000, 'paid' => 0, 'diff' => 50000,
        ]);

        // isian 0 / kosong diperbolehkan (mungkin memang belum bayar sama sekali)
        $this->post('/pos/transaksi/' . $sale->id . '/bayar', [
            'bayar_kemarin' => '',
            'bayar_hari_ini' => '',
        ])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(0, $sale->paid);
        $this->assertSame(50000, $sale->diff);
        $this->assertSame(0, CustomerLedger::where('sale_id', $sale->id)
            ->where('type', 'bayar')->count());

        // tombol Bayar dipencet (meski nominal 0/kosong) → tandai selesai, tombol jadi abu
        $this->assertTrue($sale->is_paid_btn_clicked);

        $name = $sale->name;
        $html = $this->get('/pos?tab=transaksi&tx_date=2026-09-30')->getContent();
        $this->assertMatchesRegularExpression(
            '/class="ghost btn-paid-done"[^>]*title="Buka modal pembayaran ' . preg_quote($name, '/') . '"/',
            $html
        );
    }

    public function test_zero_payment_keeps_button_gray_after_edit(): void
    {
        $name = 'AbuEdit_' . uniqid();
        $product = Product::create(['name' => 'P_' . uniqid(), 'price' => 10000]);
        $sale = Sale::create([
            'date' => now()->toDateString(), 'name' => $name, 'raw_total' => 0,
            'rounded_total' => 0, 'paid' => 0, 'diff' => 0, 'is_paid_btn_clicked' => false,
        ]);
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id,
            'name' => $product->name, 'qty' => 1, 'price' => 10000]);

        // Bayar dengan nominal 0 → status abu
        $this->post('/pos/transaksi/' . $sale->id . '/bayar', [
            'bayar_hari_ini' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($sale->refresh()->is_paid_btn_clicked);

        // edit transaksi (mis. ubah qty) tidak boleh menurunkan status abu kembali
        $this->post('/pos/transaksi', [
            'editing_sale_id' => $sale->id,
            'txName' => $name,
            'txDate' => now()->toDateString(),
            'qty' => [$product->id => '2'],
            'tx_paid_touched' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($sale->refresh()->is_paid_btn_clicked,
            'Edit tidak boleh mereset status abu yang sudah di-set lewat modal Bayar.');

        $html = $this->get('/pos?tab=transaksi')->getContent();
        $this->assertMatchesRegularExpression(
            '/class="ghost btn-paid-done"[^>]*title="Buka modal pembayaran ' . preg_quote($name, '/') . '"/',
            $html
        );

        $product->delete();
    }

    public function test_repaying_replaces_previous_payment(): void
    {
        $name = 'Koreksi_' . uniqid();
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => $name, 'raw_total' => 50000,
            'rounded_total' => 50000, 'paid' => 0, 'diff' => 50000,
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $name, 'amount' => 50000,
            'type' => 'tambah', 'note' => 'Transaksi', 'sale_id' => $sale->id,
        ]);

        // bayar 15rb dulu
        $this->post('/pos/transaksi/' . $sale->id . '/bayar', [
            'bayar_hari_ini' => '15.000',
        ])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(15000, $sale->paid);
        $this->assertSame(35000, $sale->diff);

        // koreksi: input 18rb → input terakhir MENANG (bukan 15rb + 18rb = 33rb)
        $this->post('/pos/transaksi/' . $sale->id . '/bayar', [
            'bayar_hari_ini' => '18.000',
        ])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(18000, $sale->paid, 'Pembayaran kedua harus menggantikan, bukan menumpuk.');
        $this->assertSame(32000, $sale->diff);

        // pembayaran tidak menulis ledger sama sekali
        $this->assertSame(0, CustomerLedger::where('sale_id', $sale->id)
            ->where('type', 'bayar')->count());
    }

    public function test_dibayar_column_shows_sum_of_both_forms(): void
    {
        $name = 'DuaForm_' . uniqid();
        Sale::create([
            'date' => now()->toDateString(), 'name' => $name, 'raw_total' => 100000,
            'rounded_total' => 100000, 'paid' => 40000, 'paid_kemarin' => 60000, 'diff' => 60000,
        ]);

        $html = $this->get('/pos?tab=transaksi')->getContent();

        // kolom Dibayar = bayar kemarin + bayar hari ini (kedua form), bukan paid saja
        $this->assertMatchesRegularExpression(
            '/<td class="num" title="Bayar kemarin Rp60\.000 \+ hari ini Rp40\.000"\s*:title="\$store\.pay\.dibayarTitle\(\d+\)">Rp100\.000<\/td>/',
            $html
        );
        // status tetap memakai diff transaksi ini (Kurang Rp60.000)
        $this->assertMatchesRegularExpression(
            '/class="badge debt"[^>]*>Kurang Rp60\.000/s',
            $html
        );
    }

    public function test_repaying_yesterday_replaces_previous_kemarin_payment(): void
    {
        $name = 'KoreksiKmr_' . uniqid();
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => $name, 'raw_total' => 50000,
            'rounded_total' => 50000, 'paid' => 0, 'diff' => 50000,
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $name, 'amount' => 50000,
            'type' => 'tambah', 'note' => 'Transaksi', 'sale_id' => $sale->id,
        ]);

        // bayar kemarin 15rb
        $this->post('/pos/transaksi/' . $sale->id . '/bayar', [
            'pakai_kemarin' => 1, 'bayar_kemarin' => '15.000', 'bayar_hari_ini' => '0',
        ])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(15000, $sale->paid_kemarin);

        // koreksi: ganti jadi 17rb → input terakhir MENANG (bukan 15k+17k = 32k)
        $this->post('/pos/transaksi/' . $sale->id . '/bayar', [
            'pakai_kemarin' => 1, 'bayar_kemarin' => '17.000', 'bayar_hari_ini' => '0',
        ])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(17000, $sale->paid_kemarin, 'Koreksi bayar kemarin harus menggantikan, bukan menumpuk.');

        // pembayaran tidak menulis ledger sama sekali (entri "tambah" fixture juga tak disentuh)
        $this->assertSame(0, CustomerLedger::where('sale_id', $sale->id)
            ->where('type', 'bayar')->count());
        $this->assertSame(1, CustomerLedger::where('sale_id', $sale->id)
            ->where('type', 'tambah')->count());
    }

    public function test_unchecked_kemarin_moves_money_to_paid(): void
    {
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => 'KmrOff_' . uniqid(),
            'raw_total' => 50000, 'rounded_total' => 50000,
            'paid' => 0, 'paid_kemarin' => 25000, 'diff' => 50000,
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $sale->name, 'amount' => 25000,
            'type' => 'bayar', 'note' => 'Bayar kemarin', 'sale_id' => $sale->id,
        ]);

        // ceklis OFF di modal → paid_kemarin dinonaktifkan (0); client sudah memindahkan
        // uangnya ke form "bayar hari ini" (10rb + 25rb = 35rb) → uang tidak hilang
        $this->post('/pos/transaksi/' . $sale->id . '/bayar', [
            'pakai_kemarin' => 0, 'bayar_hari_ini' => '35.000',
        ])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(0, $sale->paid_kemarin);
        $this->assertSame(35000, $sale->paid);
        $this->assertSame(15000, $sale->diff);
        // ledger manual lama tidak disentuh transaksi
        $this->assertSame(1, CustomerLedger::where('sale_id', $sale->id)
            ->where('note', 'Bayar kemarin')->count());

        // badge "Bayar kemarin" hilang dari render (per baris sale ini)
        $html = $this->get('/pos?tab=transaksi&tx_date=2026-09-30')->getContent();
        $row = $this->rowOfSale($html, $sale->name);
        $this->assertStringNotContainsString('Bayar kemarin ' . rupiah(25000), $row);
        $this->assertStringContainsString('Bayar kemarin ' . rupiah(0), $row);
    }

    public function test_toggle_kemarin_off_moves_money_to_paid(): void
    {
        $name = 'TglKmr_' . uniqid();
        $sale = Sale::create([
            'date' => '2026-10-01', 'name' => $name, 'raw_total' => 200000,
            'rounded_total' => 200000, 'paid' => 20000, 'paid_kemarin' => 150000, 'diff' => 180000,
        ]);

        $res = $this->postJson('/pos/transaksi/' . $sale->id . '/toggle-kemarin', ['active' => 0]);
        $res->assertOk()->assertJson(['ok' => true, 'paid' => 170000, 'paid_kemarin' => 0, 'diff' => 30000]);

        $sale->refresh();
        $this->assertSame(170000, $sale->paid, 'Uang kemarin pindah ke bayar hari ini.');
        $this->assertSame(0, $sale->paid_kemarin);
        $this->assertSame(30000, $sale->diff);
        // tombol Bayar tidak berubah; ledger tidak disentuh
        $this->assertFalse($sale->is_paid_btn_clicked);
        $this->assertSame(0, CustomerLedger::where('sale_id', $sale->id)->count());

        // render ulang: badge hilang di baris ini, status ikut hitungan baru
        $html = $this->get('/pos?tab=transaksi&tx_date=2026-10-01')->getContent();
        $row = $this->rowOfSale($html, $name);
        $this->assertStringNotContainsString('Bayar kemarin ' . rupiah(150000), $row);
        $this->assertStringContainsString('Kurang ' . rupiah(30000), $row);
    }

    public function test_toggle_kemarin_on_is_noop_and_off_twice_is_stable(): void
    {
        $sale = Sale::create([
            'date' => '2026-10-01', 'name' => 'TglOn_' . uniqid(),
            'raw_total' => 100000, 'rounded_total' => 100000,
            'paid' => 0, 'paid_kemarin' => 0, 'diff' => 100000,
        ]);

        // ON tanpa uang kemarin = tidak mengubah apa pun (nilai baru tercatat lewat modal)
        $this->postJson('/pos/transaksi/' . $sale->id . '/toggle-kemarin', ['active' => 1])
            ->assertOk()->assertJson(['paid' => 0, 'paid_kemarin' => 0]);

        // OFF lagi saat paid_kemarin sudah 0 = idempoten (tidak menduplikasi uang)
        $this->postJson('/pos/transaksi/' . $sale->id . '/toggle-kemarin', ['active' => 0])
            ->assertOk()->assertJson(['paid' => 0, 'paid_kemarin' => 0, 'diff' => 100000]);
        $sale->refresh();
        $this->assertSame(0, $sale->paid);
        $this->assertSame(100000, $sale->diff);
    }

    public function test_bayar_lunas_keeps_paid_kemarin(): void
    {
        $name = 'LunasKmr_' . uniqid();
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => $name, 'raw_total' => 150000,
            'rounded_total' => 150000, 'paid' => 0, 'paid_kemarin' => 50000, 'diff' => 150000,
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $name, 'amount' => 150000,
            'type' => 'tambah', 'note' => 'Transaksi', 'sale_id' => $sale->id,
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $name, 'amount' => 50000,
            'type' => 'bayar', 'note' => 'Bayar kemarin', 'sale_id' => $sale->id,
        ]);

        $this->post('/pos/transaksi/' . $sale->id . '/bayar', ['lunas' => 1])
            ->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(150000, $sale->paid);
        $this->assertSame(0, $sale->diff);
        $this->assertSame(50000, $sale->paid_kemarin);

        // bayar lunas juga tidak menulis ledger — entri fixture "Bayar kemarin" tetap,
        // tapi tidak ada entri "Pembayaran lunas transaksi" baru
        $bayar = CustomerLedger::where('sale_id', $sale->id)->where('type', 'bayar')->pluck('amount', 'note');
        $this->assertSame(50000, $bayar['Bayar kemarin']);
        $this->assertArrayNotHasKey('Pembayaran lunas transaksi', $bayar);
    }

    public function test_bayar_lunas_is_rejected_for_payment_only_row(): void
    {
        $name = 'LunasNol_' . uniqid();
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => $name, 'raw_total' => 0,
            'rounded_total' => 0, 'paid' => 30000, 'diff' => -30000,
        ]);

        $this->post('/pos/transaksi/' . $sale->id . '/bayar', ['lunas' => 1])
            ->assertSessionHas('error');

        $sale->refresh();
        $this->assertSame(30000, $sale->paid, 'Paid tidak boleh tertimpa jadi 0.');
        $this->assertSame(0, CustomerLedger::where('sale_id', $sale->id)
            ->where('note', 'Pembayaran lunas transaksi')->count());
    }

    public function test_load_for_payment_keeps_paid_for_payment_only_row(): void
    {
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => 'EditBayar_' . uniqid(),
            'raw_total' => 0, 'rounded_total' => 0, 'paid' => 45000, 'diff' => -45000,
        ]);

        $response = $this->post('/pos/transaksi/' . $sale->id . '/load-for-payment');

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('tx_paid=45000', $location);
    }

    public function test_load_for_payment_prefills_current_paid_for_edit(): void
    {
        // edit baris tagihan menampilkan paid sekarang (input terakhir menang saat disimpan)
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => 'EditTagihan_' . uniqid(),
            'raw_total' => 50000, 'rounded_total' => 50000, 'paid' => 15000, 'diff' => 35000,
        ]);

        $response = $this->post('/pos/transaksi/' . $sale->id . '/load-for-payment');

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('tx_paid=15000', $location);
    }

    public function test_pay_payload_has_no_prefill(): void
    {
        $name = 'Prefill_' . uniqid();
        Sale::create([
            'date' => '2026-09-29', 'name' => $name, 'raw_total' => 180000,
            'rounded_total' => 180000, 'paid' => 80000, 'diff' => 100000,
        ]);
        Sale::create([
            'date' => '2026-09-30', 'name' => $name, 'raw_total' => 150000,
            'rounded_total' => 150000, 'paid' => 0, 'diff' => 150000,
        ]);

        $html = $this->get('/pos?tab=transaksi&tx_date=2026-09-30')->getContent();

        // revisi besar: tanpa prefill — payload tidak lagi membawa prefill_kemarin,
        // field bayar hari ini default 0; ceklis "Bayar kemarin" di-seed dari data
        // (ON bila paid_kemarin > 0) supaya tetap ON setelah reload, bukan hilang
        $this->assertStringNotContainsString('"prefill_kemarin"', $html);
        $this->assertStringContainsString('window.POS_PAY', $html);

        $js = (string) file_get_contents(base_path('public/js/pos-pay.js'));
        $this->assertStringContainsString('kemarinSeed', $js);
        $this->assertStringContainsString('paid_kemarin || 0) > 0', $js);
        $this->assertStringContainsString('String(s.paid_kemarin)', $js);
    }

    public function test_recap_diterima_includes_paid_kemarin(): void
    {
        $name = 'RecapPay_' . uniqid();
        Sale::create([
            'date' => now()->toDateString(), 'name' => $name, 'raw_total' => 0,
            'rounded_total' => 0, 'paid' => 20777, 'paid_kemarin' => 13333, 'diff' => -20777,
        ]);

        $html = $this->get('/pos')->getContent();

        // "Diterima" = sum(paid) + sum(paid_kemarin) → uang kemarin ikut dihitung hari ini
        $salesToday = Sale::where('date', now()->toDateString())->get();
        $expected = rupiah((int) $salesToday->sum('paid') + (int) $salesToday->sum('paid_kemarin'));

        $this->assertMatchesRegularExpression(
            '/Diterima<\/span>\s*<span class="num green">' . preg_quote($expected, '/') . '<\/span>/',
            $html
        );
        // pembayaran kemarin tidak disembunyikan dari kolom Dibayar barisnya
        $this->assertStringContainsString('Bayar kemarin ' . rupiah(13333), $html);
    }

    public function test_editing_sale_does_not_touch_ledger(): void
    {
        $product = \App\Models\Product::create(['name' => 'UjiEdit_' . uniqid(), 'price' => 10000]);
        $name = 'EditLedger_' . uniqid();
        $sale = Sale::create([
            'date' => '2026-09-30', 'name' => $name, 'raw_total' => 150000,
            'rounded_total' => 150000, 'paid' => 0, 'paid_kemarin' => 60000, 'diff' => 150000,
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $name, 'amount' => 150000,
            'type' => 'tambah', 'note' => 'Transaksi', 'sale_id' => $sale->id,
        ]);
        CustomerLedger::create([
            'date' => '2026-09-30', 'name' => $name, 'amount' => 60000,
            'type' => 'bayar', 'note' => 'Bayar kemarin', 'sale_id' => $sale->id,
        ]);

        // edit: ganti jumlah produk lalu simpan ulang
        $this->post('/pos/transaksi', [
            'editing_sale_id' => $sale->id,
            'txName' => $name,
            'txDate' => '2026-09-30',
            'qty' => [$product->id => '2'],
            'tx_paid_touched' => 0,
        ])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame(60000, $sale->paid_kemarin, 'paid_kemarin tidak boleh hilang saat edit.');
        $this->assertSame(20000, $sale->rounded_total);

        // edit TIDAK menghapus/membuat entri ledger — riwayat lama tetap utuh
        $this->assertSame(1, CustomerLedger::where('sale_id', $sale->id)->where('type', 'tambah')->count());
        $bayarKemarin = CustomerLedger::where('sale_id', $sale->id)
            ->where('type', 'bayar')->where('note', 'Bayar kemarin')->get();
        $this->assertCount(1, $bayarKemarin);
        $this->assertSame(60000, $bayarKemarin[0]->amount);

        $product->delete();
    }

    public function test_pay_button_green_when_unpaid_and_gray_when_paid(): void
    {
        $unpaid = 'BtnGreen_' . uniqid();
        $paid = 'BtnGray_' . uniqid();
        Sale::create(['date' => now()->toDateString(), 'name' => $unpaid, 'raw_total' => 0,
            'rounded_total' => 0, 'paid' => 0, 'diff' => 0, 'is_paid_btn_clicked' => false]);
        Sale::create(['date' => now()->toDateString(), 'name' => $paid, 'raw_total' => 0,
            'rounded_total' => 0, 'paid' => 0, 'diff' => 0, 'is_paid_btn_clicked' => true]);

        $html = $this->get('/pos?tab=transaksi')->getContent();

        // belum dibayar → hijau (btn-pay-open), sudah pernah dibayar → abu (btn-paid-done)
        $this->assertMatchesRegularExpression(
            '/class="ghost btn-pay-open"[^>]*title="Buka modal pembayaran ' . preg_quote($unpaid, '/') . '"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/class="ghost btn-paid-done"[^>]*title="Buka modal pembayaran ' . preg_quote($paid, '/') . '"/',
            $html
        );
    }

    public function test_pos_root_is_alpine_root_so_pay_directives_initialize(): void
    {
        // Alpine 3 hanya meng-init elemen di dalam root x-data;
        // tanpa ini @click tombol Bayar & modal tidak hidup (regresi: modal tidak muncul)
        $html = $this->get('/pos')->getContent();

        $this->assertStringContainsString('id="pos-root" x-data', $html);
        $this->assertStringContainsString('id="payModal"', $html);
        // ekspresi modal null-safe saat belum ada transaksi dipilih (sale = null saat load)
        $this->assertStringContainsString('$store.pay.sale ? $store.pay.sale.total : 0', $html);
        $this->assertStringContainsString('$store.pay.sale ? $store.pay.sale.paid : 0', $html);
        // tanpa prefill: ekspresi lama prefill_kemarin sudah dihapus dari modal
        $this->assertStringNotContainsString('prefill_kemarin', $html);
    }

    public function test_modal_markup_and_riwayat_removed_from_table(): void
    {
        Sale::create([
            'date' => now()->toDateString(), 'name' => 'Markup_' . uniqid(),
            'raw_total' => 100000, 'rounded_total' => 100000, 'paid' => 0, 'diff' => 100000,
        ]);

        $html = $this->get('/pos?tab=transaksi')->getContent();

        $this->assertStringContainsString('window.POS_PAY', $html);
        $this->assertStringContainsString('id="payModal"', $html);
        $this->assertStringContainsString('$store.pay.open(', $html);
        $this->assertStringContainsString('Bayar kemarin', $html);
        $this->assertMatchesRegularExpression('/pos-pay\.js\?v=\d+/', $html);

        // revisi: baris total semua pelanggan & tombol Tutup dihapus (tutup lewat X saja)
        $this->assertStringNotContainsString('Total hutang semua pelanggan', $html);
        $this->assertStringNotContainsString('class="ghost" @click="$store.pay.close()">Tutup</button>', $html);

        // tombol Riwayat sudah dihapus dari tabel transaksi maupun tab hasil
        // (akses detail hanya lewat URL ?hp_detail=Nama bila dibuka manual)
        $start = strpos($html, 'id="tab-transaksi"');
        $end = strpos($html, 'id="tab-produk"');
        $slice = substr($html, $start, $end - $start);
        $this->assertStringNotContainsString('hp_detail', $slice);
        $this->assertStringNotContainsString('hp_detail', substr($html, $end));
    }
}
