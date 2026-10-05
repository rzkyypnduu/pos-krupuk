<?php

namespace Tests\Feature;

use App\Models\CustomerLedger;
use App\Models\Expense;
use App\Models\InputLog;
use App\Models\Product;
use App\Models\Sale;
use Tests\TestCase;

/**
 * Test perbaikan bug inspeksi keterbacaan (commit phase 1):
 * C1 reset bulan baca hidden input POST, C2 fmtKg (lihat Unit\FmtKgTest),
 * C3 validasi adjustTotalDebt, C4 edit ID salah tidak boleh "berhasil" palsu.
 * Semua data uji memakai tanggal 2099-12 supaya tidak menyentuh data lain,
 * dan dibersihkan di akhir tiap test.
 */
class BugfixTest extends TestCase
{
    public function test_reset_month_reads_bulan_from_post_body(): void
    {
        $sale = Sale::create([
            'date' => '2099-12-15', 'name' => 'UjiResetBulan',
            'raw_total' => 1000, 'rounded_total' => 1000, 'paid' => 0, 'diff' => 1000,
        ]);
        $expense = Expense::create(['date' => '2099-12-15', 'amount' => 500]);
        $otherMonth = Sale::create([
            'date' => '2099-11-15', 'name' => 'UjiResetBulanLain',
            'raw_total' => 2000, 'rounded_total' => 2000, 'paid' => 0, 'diff' => 2000,
        ]);

        try {
            // Seperti form di tab Ringkasan: bulan dikirim sebagai hidden input POST.
            $response = $this->post(route('pos.resetMonth'), ['bulan' => '2099-12']);

            $response->assertRedirect();
            $response->assertSessionMissing('errors');
            $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
            $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
            // bulan lain tidak boleh tersentuh
            $this->assertDatabaseHas('sales', ['id' => $otherMonth->id]);
        } finally {
            Sale::whereIn('id', [$sale->id, $otherMonth->id])->delete();
            Expense::where('id', $expense->id)->delete();
        }
    }

    public function test_reset_month_without_bulan_fails_and_keeps_data(): void
    {
        $sale = Sale::create([
            'date' => '2099-12-16', 'name' => 'UjiResetKosong',
            'raw_total' => 1000, 'rounded_total' => 1000, 'paid' => 0, 'diff' => 1000,
        ]);

        try {
            $response = $this->post(route('pos.resetMonth'), []);

            $response->assertSessionHasErrors('bulan');
            $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        } finally {
            $sale->delete();
        }
    }

    public function test_adjust_total_debt_rejects_missing_or_invalid_input(): void
    {
        $this->postJson(route('pos.adjustTotalDebt'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'new_total']);

        $this->postJson(route('pos.adjustTotalDebt'), ['name' => 'UjiC3'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['new_total']);

        $this->postJson(route('pos.adjustTotalDebt'), ['new_total' => 5000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->postJson(route('pos.adjustTotalDebt'), ['name' => 'UjiC3', 'new_total' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['new_total']);
    }

    public function test_adjust_total_debt_valid_input_still_works(): void
    {
        try {
            $response = $this->postJson(route('pos.adjustTotalDebt'), [
                'name' => 'UjiC3Sehat', 'new_total' => 5000, 'tx_date' => '2099-12-15',
            ]);

            $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('totalSisa', 5000);
        } finally {
            CustomerLedger::where('name', 'UjiC3Sehat')->delete();
        }
    }

    public function test_edit_with_missing_sale_id_is_rejected_without_fake_success(): void
    {
        $product = Product::first();
        $this->assertNotNull($product, 'butuh minimal satu produk di DB testing');

        $before = Sale::count();

        $response = $this->post(route('pos.simpanTransaksi'), [
            'txName' => 'UjiC4Hantu',
            'txDate' => '2099-12-15',
            'qty' => [$product->id => 1],
            'editing_sale_id' => 999999,
            'tx_paid_touched' => 0,
        ]);

        $response->assertSessionHasErrors('editing_sale_id');
        // tidak ada transaksi tersimpan, tidak ada pesan sukses palsu
        $this->assertSame($before, Sale::count());
        $response->assertSessionMissing('success');
        $this->assertDatabaseMissing('sales', ['name' => 'UjiC4Hantu']);
    }

    public function test_edit_with_valid_sale_id_still_saves(): void
    {
        $product = Product::first();
        $this->assertNotNull($product, 'butuh minimal satu produk di DB testing');

        $sale = Sale::create([
            'date' => '2099-12-17', 'name' => 'UjiC4Asli',
            'raw_total' => 0, 'rounded_total' => 0, 'paid' => 0, 'diff' => 0,
        ]);

        try {
            $response = $this->post(route('pos.simpanTransaksi'), [
                'txName' => 'UjiC4AsliEdit',
                'txDate' => '2099-12-17',
                'qty' => [$product->id => 1],
                'editing_sale_id' => $sale->id,
                'tx_paid_touched' => 0,
            ]);

            $response->assertSessionHas('success');
            $response->assertSessionHasNoErrors();
            $this->assertDatabaseHas('sales', ['id' => $sale->id, 'name' => 'UjiC4AsliEdit']);
        } finally {
            Sale::where('name', 'UjiC4AsliEdit')->delete();
            InputLog::where('sale_id', $sale->id)->delete();
        }
    }
}
