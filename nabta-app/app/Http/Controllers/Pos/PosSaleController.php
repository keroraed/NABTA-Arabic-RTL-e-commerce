<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosSale;
use App\Models\PosTerminal;
use App\Services\Pos\ReceiptSequencer;
use App\Services\Pos\SaleRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Takes a completed sale from the till.
 *
 * This is a session-authenticated, CSRF-protected web route that answers JSON —
 * not an API layer. It exists because a sale is a transaction with a result the
 * cashier has to see, and an HTML form post cannot carry a cart.
 *
 * The shape it accepts is deliberately the same one the offline outbox will
 * upload later: a client-generated uuid, a receipt number, and line items. That
 * way adding the offline layer is a client change, not a server rewrite.
 */
class PosSaleController extends Controller
{
    public function __construct(
        private readonly SaleRegistrar $registrar,
        private readonly ReceiptSequencer $sequencer,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $terminal = $this->currentTerminal($request);

        if (! $terminal) {
            return response()->json(['message' => 'لم يتم اختيار الكاشير'], 422);
        }

        $shift = $terminal->openShift();

        if (! $shift) {
            return response()->json(['message' => 'لا توجد وردية مفتوحة'], 422);
        }

        $data = $request->validate([
            'uuid' => ['required', 'uuid'],
            'payment_method' => ['required', Rule::in([PosSale::CASH, PosSale::CREDIT, PosSale::PARTIAL])],
            'party_id' => ['nullable', 'exists:parties,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_cash' => ['nullable', 'numeric', 'min:0'],
            'paid_on_credit' => ['nullable', 'numeric', 'min:0'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'claimed_total' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        // A credit sale with no customer would create a debt owed by nobody.
        if (in_array($data['payment_method'], [PosSale::CREDIT, PosSale::PARTIAL], true)
            && empty($data['party_id'])) {
            return response()->json(['message' => 'البيع الآجل يتطلب اختيار عميل'], 422);
        }

        $data['receipt_number'] = $this->sequencer->next($terminal);

        try {
            $sale = $this->registrar->register($terminal, $data, $shift, $request->user()->id);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage() ?: 'تعذّر حفظ الفاتورة'], 422);
        }

        return response()->json([
            'uuid' => $sale->uuid,
            'receipt_number' => $sale->receipt_number,
            'total' => (float) $sale->total,
            'change_given' => (float) $sale->change_given,
            'receipt_url' => route('pos.receipt', $sale),
        ]);
    }

    private function currentTerminal(Request $request): ?PosTerminal
    {
        $id = $request->session()->get(PosController::TERMINAL_SESSION_KEY);

        return $id ? PosTerminal::active()->find($id) : null;
    }
}
