<?php

namespace Tests\Unit;

use App\Services\Pos\PosPricing;
use PHPUnit\Framework\TestCase;

/**
 * The money maths, tested in isolation with no database.
 *
 * This is the part that must not be wrong: a rounding error here is a drawer
 * that is a few piastres short at the end of every day, with nothing in the
 * ledger to point at.
 */
class PosPricingTest extends TestCase
{
    private PosPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new PosPricing(1);
    }

    public function test_it_converts_pounds_to_piastres_without_float_drift(): void
    {
        $this->assertSame(2400, $this->pricing->toPiastres(24.00));
        $this->assertSame(1005, $this->pricing->toPiastres(10.05));
        // 0.1 + 0.2 territory: the classic float trap.
        $this->assertSame(30, $this->pricing->toPiastres(0.30));
    }

    /** The sample label from the shop: 240 EGP/kg at 100 g should be 24.00. */
    public function test_it_prices_the_sample_label_from_the_shop(): void
    {
        $this->assertSame(2400, $this->pricing->weightedLinePiastres(240.00, 100));
    }

    public function test_it_prices_a_weighted_line(): void
    {
        // 85.50/kg at 1.250 kg = 106.875 -> 106.88
        $this->assertSame(10688, $this->pricing->weightedLinePiastres(85.50, 1250));
    }

    public function test_it_prices_a_boundary_weight(): void
    {
        // The scale tops out at 15 kg; the parser and the maths must both cope.
        $this->assertSame(150000, $this->pricing->weightedLinePiastres(100.00, 15000));
    }

    public function test_it_prices_a_unit_line(): void
    {
        $this->assertSame(5400, $this->pricing->unitLinePiastres(18.00, 3));
    }

    /**
     * The scale prints its own price on the sticker. If the POS rounds
     * differently, the receipt disagrees with the label in the customer's hand.
     */
    public function test_it_rounds_to_the_configured_step(): void
    {
        $toFive = new PosPricing(5);

        $this->assertSame(2405, $toFive->roundToStep(2403));
        $this->assertSame(2400, $toFive->roundToStep(2402));
    }

    public function test_totals_sum_lines_and_apply_discounts(): void
    {
        $totals = $this->pricing->totals([
            ['gross_piastres' => 10000, 'discount_piastres' => 500],
            ['gross_piastres' => 2500],
        ], saleDiscountPiastres: 1000);

        $this->assertSame(12500, $totals['subtotal']);
        $this->assertSame(500, $totals['line_discounts']);
        $this->assertSame(1000, $totals['sale_discount']);
        $this->assertSame(11000, $totals['total']);
    }

    /**
     * A cashier typing 5000 instead of 50 must not produce a negative sale that
     * then has to be unpicked from the ledger and the drawer.
     */
    public function test_a_discount_can_never_drive_the_total_below_zero(): void
    {
        $totals = $this->pricing->totals(
            [['gross_piastres' => 5000]],
            saleDiscountPiastres: 500000,
        );

        $this->assertSame(0, $totals['total']);
        $this->assertSame(5000, $totals['sale_discount']);
    }

    public function test_change_is_never_negative(): void
    {
        $this->assertSame(2500, $this->pricing->changeDue(7500, 10000));
        $this->assertSame(0, $this->pricing->changeDue(7500, 5000));
    }
}
