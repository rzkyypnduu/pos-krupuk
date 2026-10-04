<?php

namespace Tests\Unit;

use App\Services\SpeechParser;
use PHPUnit\Framework\TestCase;

class SpeechParserTest extends TestCase
{
    private SpeechParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpeechParser();
    }

    /** @return array<int, object> */
    private function products(): array
    {
        return [
            (object) ['id' => 1, 'name' => 'Kuning', 'price' => 10000],
            (object) ['id' => 2, 'name' => 'Lempeng', 'price' => 12000],
            (object) ['id' => 3, 'name' => 'Kecil OM', 'price' => 15000],
            (object) ['id' => 4, 'name' => 'Kecil 2', 'price' => 11000],
            (object) ['id' => 5, 'name' => 'TG', 'price' => 9000],
            (object) ['id' => 6, 'name' => 'TG Mini', 'price' => 8000],
        ];
    }

    public function test_parses_customer_name_and_single_product(): void
    {
        $result = $this->parser->parse('Budi dua kilo kuning', $this->products());

        $this->assertSame('Budi', $result['name']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Kuning', $result['items'][0]['name']);
        $this->assertEqualsWithDelta(2.0, $result['items'][0]['qty'], 0.001);
        $this->assertSame(20000, $result['total']);
        $this->assertNull($result['note']);
    }

    public function test_parses_multiple_products_in_one_sentence(): void
    {
        $result = $this->parser->parse('Siti dua kilo kuning satu setengah lempeng', $this->products());

        $this->assertSame('Siti', $result['name']);
        $this->assertCount(2, $result['items']);
        $this->assertSame('Kuning', $result['items'][0]['name']);
        $this->assertEqualsWithDelta(2.0, $result['items'][0]['qty'], 0.001);
        $this->assertSame('Lempeng', $result['items'][1]['name']);
        $this->assertEqualsWithDelta(1.5, $result['items'][1]['qty'], 0.001);
        $this->assertSame(38000, $result['total']);
        $this->assertEqualsWithDelta(3.5, $result['total_kg'], 0.001);
    }

    public function test_number_words_and_decimal_are_normalized(): void
    {
        $result = $this->parser->parse('Andi dua puluh lima koma lima kilo kuning', $this->products());

        $this->assertSame('Andi', $result['name']);
        $this->assertEqualsWithDelta(25.5, $result['items'][0]['qty'], 0.001);
    }

    public function test_matches_typo_product_with_levenshtein(): void
    {
        $result = $this->parser->parse('Budi tiga kunign', $this->products());

        $this->assertCount(1, $result['items']);
        $this->assertSame('Kuning', $result['items'][0]['name']);
        $this->assertEqualsWithDelta(3.0, $result['items'][0]['qty'], 0.001);
        $this->assertSame('tight', $result['items'][0]['confidence']);
    }

    public function test_prefers_longest_product_name(): void
    {
        $result = $this->parser->parse('Andi satu tg mini', $this->products());

        $this->assertCount(1, $result['items']);
        $this->assertSame('TG Mini', $result['items'][0]['name']);
    }

    public function test_defaults_to_one_when_no_quantity_is_spoken(): void
    {
        $result = $this->parser->parse('Rina kuning', $this->products());

        $this->assertSame('Rina', $result['name']);
        $this->assertEqualsWithDelta(1.0, $result['items'][0]['qty'], 0.001);
    }

    public function test_parses_quantity_before_product_name(): void
    {
        $result = $this->parser->parse('Dewi empat lempeng', $this->products());

        $this->assertSame('Dewi', $result['name']);
        $this->assertSame('Lempeng', $result['items'][0]['name']);
        $this->assertEqualsWithDelta(4.0, $result['items'][0]['qty'], 0.001);
    }

    public function test_unit_converts_to_kilograms(): void
    {
        $result = $this->parser->parse('Tono dua ons kuning', $this->products());

        $this->assertSame('Tono', $result['name']);
        $this->assertEqualsWithDelta(0.2, $result['items'][0]['qty'], 0.001);
        $this->assertSame('ons', $result['items'][0]['unit']);
    }

    public function test_returns_empty_items_when_no_product_mentioned(): void
    {
        $result = $this->parser->parse('Halo selamat pagi', $this->products());

        $this->assertSame([], $result['items']);
        $this->assertSame(0, $result['total']);
        $this->assertSame('Halo selamat pagi', $result['name']);
    }

    public function test_fillers_are_removed_from_customer_name(): void
    {
        $result = $this->parser->parse('eh maaf saya mau beli pak Budi dua kuning', $this->products());

        $this->assertSame('Budi', $result['name']);
        $this->assertEqualsWithDelta(2.0, $result['items'][0]['qty'], 0.001);
    }

    public function test_trailing_text_becomes_note(): void
    {
        $result = $this->parser->parse('Budi dua kuning bungkus terpisah', $this->products());

        $this->assertSame('Budi', $result['name']);
        $this->assertSame('bungkus terpisah', $result['note']);
    }

    public function test_product_alias_with_nya_suffix_is_matched(): void
    {
        $result = $this->parser->parse('Budi duanya kuning', $this->products());

        $this->assertCount(1, $result['items']);
        $this->assertSame('Kuning', $result['items'][0]['name']);
        $this->assertEqualsWithDelta(2.0, $result['items'][0]['qty'], 0.001);
    }

    public function test_raw_and_normalized_text_are_kept_for_logging(): void
    {
        $raw = 'Budi dua kuning';
        $result = $this->parser->parse($raw, $this->products());

        $this->assertSame($raw, $result['raw']);
        $this->assertSame('budi 2 kuning', $result['normalized']);
    }

    public function test_loose_pass_matches_noisy_product_word(): void
    {
        $products = array_merge($this->products(), [
            (object) ['id' => 7, 'name' => 'Gelung', 'price' => 9000],
        ]);

        $result = $this->parser->parse('Budi dua kilo belum', $products);

        $this->assertCount(1, $result['items']);
        $this->assertSame('Gelung', $result['items'][0]['name']);
        $this->assertEqualsWithDelta(2.0, $result['items'][0]['qty'], 0.001);
        $this->assertSame('loose', $result['items'][0]['confidence']);
        $this->assertSame('Budi', $result['name']);
    }

    public function test_repeated_product_is_overwritten_not_summed(): void
    {
        $result = $this->parser->parse('Budi dua kuning tiga kuning', $this->products());

        $this->assertCount(1, $result['items']);
        $this->assertEqualsWithDelta(3.0, $result['items'][0]['qty'], 0.001);
        $this->assertSame(30000, $result['total']);
        $this->assertSame('Budi', $result['name']);
    }

    public function test_loose_pass_does_not_touch_numbers_or_units(): void
    {
        $result = $this->parser->parse('Budi dua kilo kuning', $this->products());

        $this->assertCount(1, $result['items']);
        $this->assertSame('Kuning', $result['items'][0]['name']);
        $this->assertSame('exact', $result['items'][0]['confidence']);
    }

    public function test_half_alone_is_half(): void
    {
        $result = $this->parser->parse('Budi setengah kuning', $this->products());

        $this->assertEqualsWithDelta(0.5, $result['items'][0]['qty'], 0.001);
    }

    public function test_digit_followed_by_setengah_is_added(): void
    {
        $result = $this->parser->parse('Budi 2 setengah kuning', $this->products());

        $this->assertEqualsWithDelta(2.5, $result['items'][0]['qty'], 0.001);
    }

    public function test_half_after_unit_is_added_to_number(): void
    {
        $result = $this->parser->parse('Budi dua kilo setengah kuning', $this->products());

        $this->assertEqualsWithDelta(2.5, $result['items'][0]['qty'], 0.001);
    }

    public function test_three_and_a_half_variants(): void
    {
        foreach ([['Budi 3 setengah kuning', 3.5], ['Budi tiga setengah kuning', 3.5]] as [$text, $expected]) {
            $result = $this->parser->parse($text, $this->products());

            $this->assertEqualsWithDelta($expected, $result['items'][0]['qty'], 0.001, $text);
        }
    }

    public function test_digit_before_koma_word_is_joined(): void
    {
        $result = $this->parser->parse('Budi 2 koma lima kuning', $this->products());

        $this->assertEqualsWithDelta(2.5, $result['items'][0]['qty'], 0.001);
    }
}
