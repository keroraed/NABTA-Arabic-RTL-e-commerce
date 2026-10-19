<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Party;
use App\Services\Accounting\PartyLedger;
use App\Services\Accounting\Treasury;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The financial correctness the brief asks for by name: a statement that equals
 * the sum of its entries, and a treasury that reconciles.
 */
class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private function ledger(): PartyLedger
    {
        return $this->app->make(PartyLedger::class);
    }

    private function treasury(): Treasury
    {
        return $this->app->make(Treasury::class);
    }

    /* --------------------------------------------------------------------
     | Party ledger
     -------------------------------------------------------------------- */

    /**
     * The convention, asserted rather than just documented: a supplier we owe
     * money to has a positive balance from credits.
     */
    public function test_a_supplier_balance_is_credits_minus_debits(): void
    {
        $supplier = Party::create(['type' => Party::TYPE_SUPPLIER, 'name' => 'مورد المكسرات']);

        // Goods received: we now owe them.
        $this->ledger()->credit($supplier, 5000, 'فاتورة شراء #1');
        // We paid some of it back.
        $this->ledger()->debit($supplier, 2000, 'دفعة نقدية');

        $this->assertSame(3000.0, $supplier->fresh()->balance());
    }

    /** A customer who owes us money has a positive balance from debits. */
    public function test_a_customer_balance_is_debits_minus_credits(): void
    {
        $customer = Party::create(['type' => Party::TYPE_CUSTOMER, 'name' => 'أحمد']);

        $this->ledger()->debit($customer, 800, 'فاتورة آجل');
        $this->ledger()->credit($customer, 300, 'دفعة من العميل');

        $this->assertSame(500.0, $customer->fresh()->balance());
    }

    public function test_a_statement_closing_balance_equals_the_sum_of_its_entries(): void
    {
        $customer = Party::create([
            'type' => Party::TYPE_CUSTOMER,
            'name' => 'أحمد',
            'opening_balance' => 100,
        ]);

        $this->ledger()->debit($customer, 250, 'فاتورة 1');
        $this->ledger()->debit($customer, 175.50, 'فاتورة 2');
        $this->ledger()->credit($customer, 200, 'دفعة');

        $statement = $this->ledger()->statement($customer);

        $expected = 100 + 250 + 175.50 - 200;

        $this->assertSame($expected, $statement['closing']);
        $this->assertSame($expected, $customer->fresh()->balance() + 100);
        $this->assertCount(3, $statement['rows']);
    }

    /** The running balance column must actually run, row by row. */
    public function test_the_statement_carries_a_running_balance(): void
    {
        $customer = Party::create(['type' => Party::TYPE_CUSTOMER, 'name' => 'أحمد']);

        $this->ledger()->debit($customer, 100, 'أ');
        $this->ledger()->debit($customer, 50, 'ب');
        $this->ledger()->credit($customer, 30, 'ج');

        $balances = collect($this->ledger()->statement($customer)['rows'])->pluck('balance')->all();

        $this->assertSame([100.0, 150.0, 120.0], $balances);
    }

    /**
     * The ledger is append-only. A correction is a reversing entry, never an
     * edit — otherwise a statement printed yesterday stops matching today's.
     */
    public function test_ledger_entries_cannot_be_edited_or_deleted(): void
    {
        $customer = Party::create(['type' => Party::TYPE_CUSTOMER, 'name' => 'أحمد']);
        $entry = $this->ledger()->debit($customer, 100, 'فاتورة');

        $this->expectException(\RuntimeException::class);
        $entry->update(['debit' => 999]);
    }

    /* --------------------------------------------------------------------
     | Treasury
     -------------------------------------------------------------------- */

    public function test_the_treasury_reconciles(): void
    {
        $account = CashAccount::create([
            'name' => 'خزينة المحل',
            'type' => 'cash',
            'opening_balance' => 500,
        ]);

        $this->treasury()->in($account, 1200, 'مبيعات');
        $this->treasury()->in($account, 300, 'تحصيل من عميل');
        $this->treasury()->out($account, 450, 'مصروف كهرباء');

        $report = $this->treasury()->report($account);

        $this->assertTrue($report['reconciles']);
        $this->assertSame(500.0, $report['opening']);
        $this->assertSame(1500.0, $report['in']);
        $this->assertSame(450.0, $report['out']);
        $this->assertSame(1550.0, $report['closing']);

        // The model's own balance() must agree with the report.
        $this->assertSame(1550.0, $account->fresh()->balance());
    }

    /** Amounts are stored positive; direction carries the sign. */
    public function test_an_out_movement_is_stored_as_a_positive_amount(): void
    {
        $account = CashAccount::create(['name' => 'خزينة', 'type' => 'cash']);

        $movement = $this->treasury()->out($account, 250, 'مصروف');

        $this->assertSame('250.00', $movement->amount);
        $this->assertSame('out', $movement->direction);
        $this->assertSame(-250.0, $account->fresh()->balance());
    }

    public function test_cash_movements_cannot_be_edited_or_deleted(): void
    {
        $account = CashAccount::create(['name' => 'خزينة', 'type' => 'cash']);
        $movement = $this->treasury()->in($account, 100, 'مبيعات');

        $this->expectException(\RuntimeException::class);
        $movement->delete();
    }

    /**
     * A date window must not lose money: what happened before the window is the
     * opening balance, not something that quietly vanishes.
     */
    public function test_a_windowed_report_carries_prior_movements_into_the_opening(): void
    {
        $account = CashAccount::create(['name' => 'خزينة', 'type' => 'cash', 'opening_balance' => 100]);

        $this->treasury()->in($account, 500, 'قديم', occurredAt: now()->subDays(10));
        $this->treasury()->in($account, 200, 'حديث', occurredAt: now());

        $report = $this->treasury()->report($account, from: now()->subDay());

        $this->assertSame(600.0, $report['opening']);   // 100 opening + 500 prior
        $this->assertSame(200.0, $report['in']);
        $this->assertSame(800.0, $report['closing']);
        $this->assertTrue($report['reconciles']);
    }
}
