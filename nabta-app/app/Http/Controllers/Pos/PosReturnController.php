<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosSale;
use App\Models\PosTerminal;
use App\Services\Pos\ReturnRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PosReturnController extends Controller
{
    public function __construct(private readonly ReturnRegistrar $returns) {}

    /** Look up the receipt to be refunded. */
    public function search(Request $request): View|RedirectResponse
    {
        $terminal = $this->currentTerminal($request);

        if (! $terminal) {
            return redirect()->route('pos.terminal');
        }

        $receipt = trim((string) $request->query('receipt', ''));
        $sale = null;
        $error = null;

        if ($receipt !== '') {
            $sale = PosSale::query()
                ->where('type', 'sale')
                ->where('receipt_number', $receipt)
                ->with(['items.product', 'terminal', 'customer'])
                ->first();

            $error = $sale ? null : __('pos.receipt_not_found');
        }

        return view('pos.return', [
            'terminal' => $terminal,
            'receipt' => $receipt,
            'sale' => $sale,
            'error' => $error,
            // How much of each line is still refundable, after any earlier
            // partial return against the same receipt.
            'returnable' => $sale ? $this->returns->returnableQuantities($sale) : [],
        ]);
    }

    public function store(Request $request, PosSale $sale): JsonResponse
    {
        $terminal = $this->currentTerminal($request);

        if (! $terminal) {
            return response()->json(['message' => __('pos.no_terminal')], 422);
        }

        $shift = $terminal->openShift();

        if (! $shift) {
            return response()->json(['message' => __('pos.no_open_shift')], 422);
        }

        $data = $request->validate([
            'uuid' => ['required', 'uuid'],
            'refund_method' => ['required', 'in:cash,credit'],
            'reason' => ['nullable', 'string', 'max:255'],
            'quantities' => ['required', 'array', 'min:1'],
            'quantities.*' => ['numeric', 'min:0'],
        ]);

        try {
            $return = $this->returns->register(
                original: $sale,
                quantities: $data['quantities'],
                refundMethod: $data['refund_method'],
                uuid: $data['uuid'],
                shift: $shift,
                userId: $request->user()->id,
                reason: $data['reason'] ?? null,
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage() ?: __('pos.return_failed')], 422);
        }

        return response()->json([
            'receipt_number' => $return->receipt_number,
            // Stored negative; the screen wants the amount handed back.
            'refunded' => abs((float) $return->total),
            'receipt_url' => route('pos.receipt', $return),
        ]);
    }

    private function currentTerminal(Request $request): ?PosTerminal
    {
        $id = $request->session()->get(PosController::TERMINAL_SESSION_KEY);

        return $id ? PosTerminal::active()->find($id) : null;
    }
}
