<?php

namespace App\Services\Accounting;

use App\Models\LedgerEntry;
use App\Models\Party;
use Illuminate\Database\Eloquent\Model;

/**
 * The only way a party balance changes.
 *
 * The debit/credit convention, which the whole system depends on being applied
 * consistently:
 *
 *   مدين / debit  = the party owes us less, or we paid them
 *   دائن / credit = we owe them more
 *
 *   supplier balance owed by us  = SUM(credit) - SUM(debit)
 *   customer balance owed to us  = SUM(debit)  - SUM(credit)
 *
 * So: a credit sale debits the customer (they now owe us). A goods receipt
 * credits the supplier (we now owe them). A payment runs the other way.
 *
 * Exactly one of debit/credit is non-zero on any row. Two columns rather than
 * one signed column, because that is the shape the Arabic statement wants.
 */
class PartyLedger
{
    public function debit(
        Party|int $party,
        float $amount,
        string $description,
        ?Model $reference = null,
        ?int $cashAccountId = null,
        ?\DateTimeInterface $date = null,
    ): LedgerEntry {
        return $this->post($party, $amount, 0, $description, $reference, $cashAccountId, $date);
    }

    public function credit(
        Party|int $party,
        float $amount,
        string $description,
        ?Model $reference = null,
        ?int $cashAccountId = null,
        ?\DateTimeInterface $date = null,
    ): LedgerEntry {
        return $this->post($party, 0, $amount, $description, $reference, $cashAccountId, $date);
    }

    private function post(
        Party|int $party,
        float $debit,
        float $credit,
        string $description,
        ?Model $reference,
        ?int $cashAccountId,
        ?\DateTimeInterface $date,
    ): LedgerEntry {
        return LedgerEntry::create([
            'party_id' => $party instanceof Party ? $party->id : $party,
            'entry_date' => $date ?? now(),
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference?->getKey(),
            'cash_account_id' => $cashAccountId,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * A statement with a running balance column, which is the layout the client
     * expects. The running total is computed here rather than in SQL so it works
     * identically on MySQL and on the SQLite used by the test suite.
     *
     * @return array{opening: float, rows: array<int, array<string, mixed>>, closing: float}
     */
    public function statement(
        Party $party,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
    ): array {
        $sign = $party->isSupplier() ? -1 : 1;

        // With no start date there is no "before", so nothing is carried in.
        // Guarding with when() here would silently sum the WHOLE ledger into the
        // opening balance and then count every entry a second time in the rows.
        $priorDebit = 0.0;
        $priorCredit = 0.0;

        if ($from) {
            $before = $party->ledgerEntries()
                ->whereDate('entry_date', '<', $from)
                ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
                ->first();

            $priorDebit = (float) $before->d;
            $priorCredit = (float) $before->c;
        }

        // Opening carries the party's own opening_balance plus anything posted
        // before the window.
        $opening = round(
            (float) $party->opening_balance + $sign * ($priorDebit - $priorCredit),
            2,
        );

        $entries = $party->ledgerEntries()
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $running = $opening;
        $rows = [];

        foreach ($entries as $entry) {
            $running = round($running + $sign * ((float) $entry->debit - (float) $entry->credit), 2);

            $rows[] = [
                'date' => $entry->entry_date,
                'description' => $entry->description,
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
                'balance' => $running,
            ];
        }

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $running];
    }
}
