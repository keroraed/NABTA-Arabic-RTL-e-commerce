<?php

namespace App\Services\Pos;

use App\Models\PosSale;
use App\Models\PosTerminal;
use Illuminate\Support\Facades\DB;

/**
 * Receipt numbers: {terminal_code}-{6-digit sequence}, e.g. T1-000145.
 *
 * The sequence is per terminal so two terminals can never collide, which is what
 * lets the offline client generate its own numbers later without coordinating
 * with the server.
 *
 * Online, the number is allocated here under a row lock. Once the offline layer
 * lands the client will generate it instead and the server will store it as-is;
 * the unique index on (pos_terminal_id, receipt_number) catches a clash either
 * way, which is what happens if a terminal's local storage is ever wiped.
 */
class ReceiptSequencer
{
    public function next(PosTerminal $terminal): string
    {
        return DB::transaction(function () use ($terminal) {
            $last = PosSale::query()
                ->where('pos_terminal_id', $terminal->id)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('receipt_number');

            $sequence = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;

            do {
                $candidate = $this->format($terminal, $sequence);
                $sequence++;
            } while (PosSale::where('pos_terminal_id', $terminal->id)
                ->where('receipt_number', $candidate)
                ->exists());

            return $candidate;
        });
    }

    public function format(PosTerminal $terminal, int $sequence): string
    {
        return $terminal->code.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
