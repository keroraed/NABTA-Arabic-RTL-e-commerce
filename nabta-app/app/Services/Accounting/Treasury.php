<?php

namespace App\Services\Accounting;

use App\Models\CashAccount;
use App\Models\CashMovement;
use Illuminate\Database\Eloquent\Model;

/**
 * The only way money enters or leaves a cash account.
 *
 * Amounts are always stored positive; `direction` carries the sign. That keeps
 * the treasury report (opening + in - out = closing) a straight sum rather than
 * a conditional one, and makes an accidental double-negative impossible.
 */
class Treasury
{
    public function in(
        CashAccount|int $account,
        float $amount,
        string $description,
        ?Model $reference = null,
        ?\DateTimeInterface $occurredAt = null,
    ): CashMovement {
        return $this->move($account, CashMovement::IN, $amount, $description, $reference, $occurredAt);
    }

    public function out(
        CashAccount|int $account,
        float $amount,
        string $description,
        ?Model $reference = null,
        ?\DateTimeInterface $occurredAt = null,
    ): CashMovement {
        return $this->move($account, CashMovement::OUT, $amount, $description, $reference, $occurredAt);
    }

    private function move(
        CashAccount|int $account,
        string $direction,
        float $amount,
        string $description,
        ?Model $reference,
        ?\DateTimeInterface $occurredAt,
    ): CashMovement {
        return CashMovement::create([
            'cash_account_id' => $account instanceof CashAccount ? $account->id : $account,
            'direction' => $direction,
            'amount' => round(abs($amount), 2),
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference?->getKey(),
            'description' => $description,
            'occurred_at' => $occurredAt ?? now(),
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Treasury report for one account over a window.
     *
     * `reconciles` is the point of this method: opening + in - out must equal
     * closing. If it ever does not, the report says so loudly rather than
     * quietly printing a wrong number.
     *
     * @return array{opening: float, in: float, out: float, closing: float, reconciles: bool}
     */
    public function report(
        CashAccount $account,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
    ): array {
        // With no start date there is no "before", so nothing is carried in.
        // Guarding with when() here would fold every movement into the opening
        // balance and then count them all again inside the window.
        $priorIn = 0.0;
        $priorOut = 0.0;

        if ($from) {
            $priorSums = $account->movements()
                ->where('occurred_at', '<', $from)
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as i")
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as o")
                ->first();

            $priorIn = (float) $priorSums->i;
            $priorOut = (float) $priorSums->o;
        }

        $opening = round((float) $account->opening_balance + $priorIn - $priorOut, 2);

        $windowSums = $account->movements()
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as i")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as o")
            ->first();

        $in = round((float) $windowSums->i, 2);
        $out = round((float) $windowSums->o, 2);
        $closing = round($opening + $in - $out, 2);

        return [
            'opening' => $opening,
            'in' => $in,
            'out' => $out,
            'closing' => $closing,
            'reconciles' => abs(($opening + $in - $out) - $closing) < 0.005,
        ];
    }
}
