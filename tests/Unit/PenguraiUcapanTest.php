<?php

namespace Tests\Unit;

use App\Services\PenguraiUcapan;
use PHPUnit\Framework\TestCase;

class PenguraiUcapanTest extends TestCase
{
    private PenguraiUcapan $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PenguraiUcapan;
    }

    /** @return array<int, object> */
    private function products(): array
    {
        return [
            (object) ['id' => 1, 'nama' => 'Kuning', 'harga' => 10000],
            (object) ['id' => 2, 'nama' => 'Lempeng', 'harga' => 12000],
            (object) ['id' => 3, 'nama' => 'Kecil OM', 'harga' => 15000],
            (object) ['id' => 4, 'nama' => 'Kecil 2', 'harga' => 11000],
            (object) ['id' => 5, 'nama' => 'TG', 'harga' => 9000],
            (object) ['id' => 6, 'nama' => 'TG Mini', 'harga' => 8000],
        ];
    }

    public function test_parses_customer_name_and_single_product(): void
    {
        $hasil = $this->parser->urai('Budi dua kilo kuning', $this->products());

        $this->assertSame('Budi', $hasil['name']);
        $this->assertCount(1, $hasil['items']);
        $this->assertSame('Kuning', $hasil['items'][0]['name']);
        $this->assertEqualsWithDelta(2.0, $hasil['items'][0]['qty'], 0.001);
        $this->assertSame(20000, $hasil['total']);
        $this->assertNull($hasil['note']);
    }

    public function test_parses_multiple_products_in_one_sentence(): void
    {
        $hasil = $this->parser->urai('Siti dua kilo kuning satu setengah lempeng', $this->products());

        $this->assertSame('Siti', $hasil['name']);
        $this->assertCount(2, $hasil['items']);
        $this->assertSame('Kuning', $hasil['items'][0]['name']);
        $this->assertEqualsWithDelta(2.0, $hasil['items'][0]['qty'], 0.001);
        $this->assertSame('Lempeng', $hasil['items'][1]['name']);
        $this->assertEqualsWithDelta(1.5, $hasil['items'][1]['qty'], 0.001);
        $this->assertSame(38000, $hasil['total']);
        $this->assertEqualsWithDelta(3.5, $hasil['total_kg'], 0.001);
    }

    public function test_number_words_and_decimal_are_normalized(): void
    {
        $hasil = $this->parser->urai('Andi dua puluh lima koma lima kilo kuning', $this->products());

        $this->assertSame('Andi', $hasil['name']);
        $this->assertEqualsWithDelta(25.5, $hasil['items'][0]['qty'], 0.001);
    }

    public function test_matches_typo_product_with_levenshtein(): void
    {
        $hasil = $this->parser->urai('Budi tiga kunign', $this->products());

        $this->assertCount(1, $hasil['items']);
        $this->assertSame('Kuning', $hasil['items'][0]['name']);
        $this->assertEqualsWithDelta(3.0, $hasil['items'][0]['qty'], 0.001);
        $this->assertSame('tight', $hasil['items'][0]['confidence']);
    }

    public function test_prefers_longest_product_name(): void
    {
        $hasil = $this->parser->urai('Andi satu tg mini', $this->products());

        $this->assertCount(1, $hasil['items']);
        $this->assertSame('TG Mini', $hasil['items'][0]['name']);
    }

    public function test_defaults_to_one_when_no_quantity_is_spoken(): void
    {
        $hasil = $this->parser->urai('Rina kuning', $this->products());

        $this->assertSame('Rina', $hasil['name']);
        $this->assertEqualsWithDelta(1.0, $hasil['items'][0]['qty'], 0.001);
    }

    public function test_parses_quantity_before_product_name(): void
    {
        $hasil = $this->parser->urai('Dewi empat lempeng', $this->products());

        $this->assertSame('Dewi', $hasil['name']);
        $this->assertSame('Lempeng', $hasil['items'][0]['name']);
        $this->assertEqualsWithDelta(4.0, $hasil['items'][0]['qty'], 0.001);
    }

    public function test_unit_converts_to_kilograms(): void
    {
        $hasil = $this->parser->urai('Tono dua ons kuning', $this->products());

        $this->assertSame('Tono', $hasil['name']);
        $this->assertEqualsWithDelta(0.2, $hasil['items'][0]['qty'], 0.001);
        $this->assertSame('ons', $hasil['items'][0]['unit']);
    }

    public function test_returns_empty_items_when_no_product_mentioned(): void
    {
        $hasil = $this->parser->urai('Halo selamat pagi', $this->products());

        $this->assertSame([], $hasil['items']);
        $this->assertSame(0, $hasil['total']);
        $this->assertSame('Halo selamat pagi', $hasil['name']);
    }

    public function test_fillers_are_removed_from_customer_name(): void
    {
        $hasil = $this->parser->urai('eh maaf saya mau beli pak Budi dua kuning', $this->products());

        $this->assertSame('Budi', $hasil['name']);
        $this->assertEqualsWithDelta(2.0, $hasil['items'][0]['qty'], 0.001);
    }

    public function test_trailing_text_becomes_note(): void
    {
        $hasil = $this->parser->urai('Budi dua kuning bungkus terpisah', $this->products());

        $this->assertSame('Budi', $hasil['name']);
        $this->assertSame('bungkus terpisah', $hasil['note']);
    }

    public function test_product_alias_with_nya_suffix_is_matched(): void
    {
        $hasil = $this->parser->urai('Budi duanya kuning', $this->products());

        $this->assertCount(1, $hasil['items']);
        $this->assertSame('Kuning', $hasil['items'][0]['name']);
        $this->assertEqualsWithDelta(2.0, $hasil['items'][0]['qty'], 0.001);
    }

    public function test_raw_and_normalized_text_are_kept_for_logging(): void
    {
        $raw = 'Budi dua kuning';
        $hasil = $this->parser->urai($raw, $this->products());

        $this->assertSame($raw, $hasil['raw']);
        $this->assertSame('budi 2 kuning', $hasil['normalized']);
    }

    public function test_loose_pass_matches_noisy_product_word(): void
    {
        $daftarProduk = array_merge($this->products(), [
            (object) ['id' => 7, 'nama' => 'Gelung', 'harga' => 9000],
        ]);

        $hasil = $this->parser->urai('Budi dua kilo belum', $daftarProduk);

        $this->assertCount(1, $hasil['items']);
        $this->assertSame('Gelung', $hasil['items'][0]['name']);
        $this->assertEqualsWithDelta(2.0, $hasil['items'][0]['qty'], 0.001);
        $this->assertSame('loose', $hasil['items'][0]['confidence']);
        $this->assertSame('Budi', $hasil['name']);
    }

    public function test_repeated_product_is_overwritten_not_summed(): void
    {
        $hasil = $this->parser->urai('Budi dua kuning tiga kuning', $this->products());

        $this->assertCount(1, $hasil['items']);
        $this->assertEqualsWithDelta(3.0, $hasil['items'][0]['qty'], 0.001);
        $this->assertSame(30000, $hasil['total']);
        $this->assertSame('Budi', $hasil['name']);
    }

    public function test_loose_pass_does_not_touch_numbers_or_units(): void
    {
        $hasil = $this->parser->urai('Budi dua kilo kuning', $this->products());

        $this->assertCount(1, $hasil['items']);
        $this->assertSame('Kuning', $hasil['items'][0]['name']);
        $this->assertSame('exact', $hasil['items'][0]['confidence']);
    }

    public function test_half_alone_is_half(): void
    {
        $hasil = $this->parser->urai('Budi setengah kuning', $this->products());

        $this->assertEqualsWithDelta(0.5, $hasil['items'][0]['qty'], 0.001);
    }

    public function test_digit_followed_by_setengah_is_added(): void
    {
        $hasil = $this->parser->urai('Budi 2 setengah kuning', $this->products());

        $this->assertEqualsWithDelta(2.5, $hasil['items'][0]['qty'], 0.001);
    }

    public function test_half_after_unit_is_added_to_number(): void
    {
        $hasil = $this->parser->urai('Budi dua kilo setengah kuning', $this->products());

        $this->assertEqualsWithDelta(2.5, $hasil['items'][0]['qty'], 0.001);
    }

    public function test_three_and_a_half_variants(): void
    {
        foreach ([['Budi 3 setengah kuning', 3.5], ['Budi tiga setengah kuning', 3.5]] as [$teks, $diharapkan]) {
            $hasil = $this->parser->urai($teks, $this->products());

            $this->assertEqualsWithDelta($diharapkan, $hasil['items'][0]['qty'], 0.001, $teks);
        }
    }

    public function test_digit_before_koma_word_is_joined(): void
    {
        $hasil = $this->parser->urai('Budi 2 koma lima kuning', $this->products());

        $this->assertEqualsWithDelta(2.5, $hasil['items'][0]['qty'], 0.001);
    }

    /** @return array<int, object> Produk contoh untuk pengujian parser. */
    private function produkUji(): array
    {
        return [
            (object) ['id' => 101, 'nama' => 'Kabur', 'harga' => 10000],
            (object) ['id' => 102, 'nama' => 'Lempeng', 'harga' => 12000],
        ];
    }

    public function test_parses_s1_s3_with_real_product_names(): void
    {
        $kasus = [
            'Kabur satu kilo' => ['Kabur' => 1.0],
            'Lempeng satu setengah kilo' => ['Lempeng' => 1.5],
            'Dua kilo Kabur dan setengah kilo Lempeng' => ['Kabur' => 2.0, 'Lempeng' => 0.5],
        ];

        foreach ($kasus as $teks => $diharapkan) {
            $hasil = $this->parser->urai($teks, $this->produkUji());
            $this->assertNull($hasil['name'], $teks);
            $this->assertNull($hasil['note'], $teks);

            $sebenarnya = [];
            foreach ($hasil['items'] as $itemPenjualan) {
                $sebenarnya[$itemPenjualan['name']] = (float) $itemPenjualan['qty'];
            }
            foreach ($diharapkan as $nama => $jumlah) {
                $this->assertArrayHasKey($nama, $sebenarnya, $teks);
                $this->assertEqualsWithDelta($jumlah, $sebenarnya[$nama], 0.001, $teks.' -> '.$nama);
            }
        }
    }

    public function test_category_and_unit_words_do_not_become_customer_name(): void
    {
        $hasil = $this->parser->urai(
            'Budi dua kilo kerupuk Kabur dan setengah kilo Lempeng',
            $this->produkUji()
        );

        $this->assertSame('Budi', $hasil['name']);
        $this->assertNull($hasil['note']);
        $this->assertCount(2, $hasil['items']);
        $this->assertSame('Kabur', $hasil['items'][0]['name']);
        $this->assertEqualsWithDelta(2.0, $hasil['items'][0]['qty'], 0.001);
        $this->assertSame('Lempeng', $hasil['items'][1]['name']);
        $this->assertEqualsWithDelta(0.5, $hasil['items'][1]['qty'], 0.001);
    }

    public function test_category_word_is_not_used_as_note(): void
    {
        $hasil = $this->parser->urai('Kabur satu kilo kerupuk', $this->produkUji());

        $this->assertSame('Kabur', $hasil['items'][0]['name']);
        $this->assertNull($hasil['note']);
    }
}
