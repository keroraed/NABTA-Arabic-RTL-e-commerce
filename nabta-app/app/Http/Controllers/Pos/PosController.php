<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\Party;
use App\Models\PosSale;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Services\Pos\PosPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The cashier-facing screens.
 *
 * Server-rendered Blade with Alpine for the cart, per the architecture
 * constraint. The terminal is identified by a value in the session, set once
 * during setup, so a browser refresh mid-shift does not lose which till this is.
 */
class PosController extends Controller
{
    public const TERMINAL_SESSION_KEY = 'pos_terminal_id';

    /** Terminal picker. The one screen that runs without a terminal chosen. */
    public function selectTerminal(): View
    {
        return view('pos.select-terminal', [
            'terminals' => PosTerminal::active()->with('warehouse')->orderBy('code')->get(),
        ]);
    }

    public function storeTerminal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pos_terminal_id' => ['required', 'exists:pos_terminals,id'],
        ]);

        $request->session()->put(self::TERMINAL_SESSION_KEY, (int) $validated['pos_terminal_id']);

        return redirect()->route('pos.index');
    }

    /**
     * The till. Requires a terminal and an open shift — a sale with no shift
     * cannot be reconciled at the end of the day, so the screen refuses to open
     * without one rather than silently recording orphan sales.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $terminal = $this->currentTerminal($request);

        if (! $terminal) {
            return redirect()->route('pos.terminal');
        }

        $shift = $terminal->openShift();

        if (! $shift) {
            return redirect()->route('pos.shift.open');
        }

        return view('pos.index', [
            'terminal' => $terminal->load('warehouse', 'cashAccount'),
            'shift' => $shift,
            'pricing' => PosPricing::fromConfig(),
            'barcodeConfig' => config('pos.barcode'),
            'roundingStep' => (int) config('pos.rounding.step_piastres', 1),
            // The catalogue ships with the page. It is a few hundred rows at
            // most, and having it in memory is what makes scanning instant —
            // and is the same shape the offline layer will cache in IndexedDB.
            'catalogue' => $this->catalogue($terminal),
            'customers' => Party::customers()->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone'])
                ->map(fn (Party $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'phone' => $p->phone,
                    'balance' => $p->balance(),
                ]),
        ]);
    }

    public function showOpenShift(Request $request): View|RedirectResponse
    {
        $terminal = $this->currentTerminal($request);

        if (! $terminal) {
            return redirect()->route('pos.terminal');
        }

        if ($terminal->openShift()) {
            return redirect()->route('pos.index');
        }

        return view('pos.shift-open', ['terminal' => $terminal]);
    }

    public function openShift(Request $request): RedirectResponse
    {
        $terminal = $this->currentTerminal($request);

        if (! $terminal) {
            return redirect()->route('pos.terminal');
        }

        if ($terminal->openShift()) {
            return redirect()->route('pos.index');
        }

        $validated = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
        ]);

        CashShift::create([
            'pos_terminal_id' => $terminal->id,
            'opened_by' => $request->user()->id,
            'opened_at' => now(),
            'opening_float' => $validated['opening_float'],
            'status' => CashShift::OPEN,
        ]);

        return redirect()->route('pos.index');
    }

    public function showCloseShift(Request $request): View|RedirectResponse
    {
        $terminal = $this->currentTerminal($request);
        $shift = $terminal?->openShift();

        if (! $shift) {
            return redirect()->route('pos.index');
        }

        return view('pos.shift-close', [
            'terminal' => $terminal,
            'shift' => $shift,
            'expected' => $shift->expectedCash(),
            'salesCount' => $shift->sales()->count(),
        ]);
    }

    /**
     * Closing records what was actually counted against what the till expected.
     * The variance is stored, never corrected away — an unexplained shortfall is
     * the whole reason this screen exists.
     */
    public function closeShift(Request $request): RedirectResponse
    {
        $terminal = $this->currentTerminal($request);
        $shift = $terminal?->openShift();

        if (! $shift) {
            return redirect()->route('pos.index');
        }

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $expected = $shift->expectedCash();
        $counted = round((float) $validated['counted_cash'], 2);

        $shift->update([
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
            'counted_cash' => $counted,
            'expected_cash' => $expected,
            'variance' => round($counted - $expected, 2),
            'status' => CashShift::CLOSED,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('pos.shift.open')
            ->with('status', 'تم إغلاق الوردية. الفرق: '.number_format($counted - $expected, 2).' ج.م');
    }

    /** Printable receipt, also used for reprints from the sales list. */
    public function receipt(Request $request, PosSale $sale): View
    {
        return view('pos.receipt', [
            'sale' => $sale->load('items', 'terminal', 'customer', 'soldBy'),
        ]);
    }

    private function currentTerminal(Request $request): ?PosTerminal
    {
        $id = $request->session()->get(self::TERMINAL_SESSION_KEY);

        return $id ? PosTerminal::active()->find($id) : null;
    }

    /**
     * Everything the till needs to price a scan without a round trip.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalogue(PosTerminal $terminal): array
    {
        return Product::query()
            ->active()
            ->with('category')
            ->orderBy('name_ar')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name_ar,
                'category' => $p->category?->name_ar,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'plu' => $p->plu_code,
                'is_weighted' => (bool) $p->is_weighted,
                // Piastres, integer. No float money reaches the browser.
                'unit_price_piastres' => (int) round($p->effectiveUnitPrice() * 100),
                'stock' => $p->stockAt($terminal->warehouse_id),
            ])
            ->all();
    }
}
