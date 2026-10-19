<?php

namespace Tests\Unit;

use App\Exceptions\UnknownBarcodeException;
use App\Services\Pos\WeightBarcodeParser;
use PHPUnit\Framework\TestCase;

class WeightBarcodeParserTest extends TestCase
{
    private function parser(array $overrides = []): WeightBarcodeParser
    {
        return new WeightBarcodeParser(array_merge([
            'prefix_length' => 0,
            'plu_length' => 4,
            'value_length' => 5,
            'value_type' => 'weight_grams',
            'has_check_digit' => false,
        ], $overrides));
    }

    /** The one real label taken from the shop. */
    public function test_it_parses_the_sample_label(): void
    {
        $result = $this->parser()->parse('103100100');

        $this->assertTrue($result->isWeighted);
        $this->assertSame('1031', $result->plu);
        $this->assertSame(100, $result->weightGrams);
        $this->assertSame(0.1, $result->weightKg());
    }

    public function test_it_parses_a_boundary_weight(): void
    {
        // 15.000 kg — the scale's rated capacity.
        $result = $this->parser()->parse('103115000');

        $this->assertSame(15000, $result->weightGrams);
        $this->assertSame(15.0, $result->weightKg());
    }

    /**
     * An ordinary retail EAN is not an error — it is a unit product, looked up
     * on products.barcode instead of products.plu_code.
     */
    public function test_it_treats_a_different_length_as_a_plain_unit_barcode(): void
    {
        $result = $this->parser()->parse('6221031000123');

        $this->assertFalse($result->isWeighted);
        $this->assertSame('6221031000123', $result->unitBarcode);
    }

    public function test_it_rejects_non_numeric_input(): void
    {
        $this->expectException(UnknownBarcodeException::class);

        $this->parser()->parse('ABC123');
    }

    public function test_it_rejects_an_empty_barcode(): void
    {
        $this->expectException(UnknownBarcodeException::class);

        $this->parser()->parse('   ');
    }

    /** A zero-weight label would ring the item up as free. */
    public function test_it_rejects_a_zero_weight_label(): void
    {
        $this->expectException(UnknownBarcodeException::class);

        $this->parser()->parse('103100000');
    }

    public function test_try_parse_returns_null_instead_of_throwing(): void
    {
        $this->assertNull($this->parser()->tryParse('ABC'));
    }

    /**
     * The format was inferred from one sample and must be reconfigurable from
     * env alone. This is the EAN-13 in-store layout: 2 PPPPP WWWWW C.
     */
    public function test_the_format_is_driven_entirely_by_config(): void
    {
        $parser = $this->parser([
            'prefix_length' => 1,
            'plu_length' => 5,
            'value_length' => 5,
            'has_check_digit' => true,
        ]);

        $this->assertSame(12, $parser->weightedLength());

        $result = $parser->parse('210314002500');

        $this->assertTrue($result->isWeighted);
        $this->assertSame('10314', $result->plu);
        $this->assertSame(250, $result->weightGrams);
    }

    /** Some scales embed the price rather than the weight. */
    public function test_it_supports_a_price_embedded_label(): void
    {
        $result = $this->parser(['value_type' => 'price_piastres'])->parse('103102400');

        $this->assertTrue($result->isWeighted);
        $this->assertSame(2400, $result->pricePiastres);
        $this->assertNull($result->weightGrams);
    }
}
