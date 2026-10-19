{{--
  Returns.

  Refunds are computed from the price actually charged on the original receipt,
  never today's price, and never more than is still outstanding on each line.
  Both of those rules live on the server; this screen only helps the cashier
  pick quantities.
--}}
@extends('layouts.pos')
@section('title', __('pos.return_title'))

@php
    $posConfig = [
        'saleId' => $sale?->id,
        'storeUrl' => $sale ? route('pos.returns.store', $sale) : null,
        'csrf' => csrf_token(),
        'lines' => $sale ? $sale->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->product_name_snapshot,
            'is_weighted' => $item->weight_kg !== null,
            'unit_price_piastres' => (int) round((float) $item->unit_price_snapshot * 100),
            'sold' => abs($item->weight_kg !== null ? (float) $item->weight_kg : (float) $item->quantity),
            'returnable' => (float) ($returnable[$item->id] ?? 0),
            // Proportional share of any line discount, so a partial return of a
            // discounted line hands back what was paid, not the list price.
            'discount_piastres' => (int) round((float) $item->discount * 100),
        ])->values() : [],
        'hasCustomer' => (bool) $sale?->party_id,
        'roundingStep' => (int) config('pos.rounding.step_piastres', 1),
    ];
@endphp

@push('scripts')
  <script defer src="{{ asset('js/pos/returns.js') }}?v={{ filemtime(public_path('js/pos/returns.js')) }}"></script>
@endpush

@section('body')
<div class="pos-shell">
  <header class="pos-bar">
    <img src="{{ asset('assets/logo.webp') }}" alt="نبته">
    <span class="chip">{{ $terminal->code }} — {{ $terminal->name }}</span>
    <span class="chip">{{ __('pos.return_title') }}</span>
    <span class="spacer"></span>
    <a href="{{ route('pos.index') }}">{{ __('pos.back_to_till') }}</a>
  </header>

  <div style="flex:1;overflow-y:auto;padding:24px">
    <div style="max-width:900px;margin:0 auto">

      <form method="GET" class="filters" style="display:flex;gap:12px;align-items:flex-end;margin-bottom:24px">
        <div class="field" style="flex:1;margin:0">
          <label for="receipt">{{ __('pos.find_receipt') }}</label>
          <input id="receipt" name="receipt" value="{{ $receipt }}"
                 placeholder="T1-000001" autocomplete="off" autofocus>
        </div>
        <button type="submit" class="btn-confirm"
                style="border:0;border-radius:5px;padding:16px 30px;font-size:17px;font-weight:700;cursor:pointer;background:#176B3A;color:#fff;min-height:54px">
          {{ __('pos.search') }}
        </button>
      </form>

      @if ($error)
        <p class="pos-alert">{{ $error }}</p>
      @endif

      @if ($sale)
        @php($allReturned = collect($returnable)->every(fn ($q) => $q <= 0))

        <div class="pos-card" style="width:100%;padding:24px" x-data="returns()">
          <h1 style="font-size:20px;margin:0 0 4px">{{ __('pos.original_sale') }} — {{ $sale->receipt_number }}</h1>
          <p class="sub" style="margin:0 0 20px">
            {{ __('pos.sold_at') }}: {{ $sale->sold_at->format('Y/m/d H:i') }}
            @if ($sale->customer) · {{ $sale->customer->name }} @endif
            · {{ $sale->paymentLabel() }}
          </p>

          @if ($allReturned)
            <p class="pos-alert">{{ __('pos.fully_returned') }}</p>
          @else
            <table style="width:100%;border-collapse:collapse;font-size:15px">
              <thead>
                <tr style="background:#F5EBD8">
                  <th style="text-align:start;padding:10px 8px">{{ __('pos.original_sale') }}</th>
                  <th style="text-align:end;padding:10px 8px;width:110px">{{ __('pos.sold_qty') }}</th>
                  <th style="text-align:end;padding:10px 8px;width:130px">{{ __('pos.returnable') }}</th>
                  <th style="text-align:end;padding:10px 8px;width:160px">{{ __('pos.return_qty') }}</th>
                </tr>
              </thead>
              <tbody>
                <template x-for="line in lines" :key="line.id">
                  <tr style="border-bottom:1px solid rgba(14,42,27,.12)">
                    <td style="padding:12px 8px">
                      <div style="font-weight:600" x-text="line.name"></div>
                      <div style="font-size:12.5px;color:#6B4630" x-text="unitLabel(line)"></div>
                    </td>
                    <td style="text-align:end;padding:12px 8px" x-text="fmtQty(line.sold) + unitSuffix(line)"></td>
                    <td style="text-align:end;padding:12px 8px"
                        :style="line.returnable <= 0 ? 'color:#B04A4A' : ''"
                        x-text="fmtQty(line.returnable) + unitSuffix(line)"></td>
                    <td style="padding:8px">
                      <input type="number" min="0" :max="line.returnable"
                             :step="line.is_weighted ? 0.001 : 1"
                             :disabled="line.returnable <= 0"
                             x-model.number="quantities[line.id]"
                             @input="clamp(line)"
                             style="width:100%;padding:10px;border:1px solid rgba(14,42,27,.2);border-radius:4px;font-size:16px;text-align:end">
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>

            <div style="display:flex;justify-content:space-between;align-items:baseline;margin:22px 0 6px;font-size:20px;font-weight:700">
              <span>{{ __('pos.refund_total') }}</span>
              <span style="color:#176B3A" x-text="money(refundTotal)"></span>
            </div>

            <div class="field" style="margin-top:18px">
              <label>{{ __('pos.refund_method') }}</label>
              <div class="pay-methods" style="grid-template-columns:1fr 1fr">
                <button type="button" :aria-pressed="method === 'cash'" @click="method = 'cash'">
                  {{ __('pos.refund_cash') }}
                </button>
                {{-- Refunding a credit sale in cash would hand the customer
                     money they never actually paid us. --}}
                <button type="button" :aria-pressed="method === 'credit'"
                        @click="method = 'credit'" @if (! $sale->party_id) disabled @endif
                        :style="!hasCustomer ? 'opacity:.4;cursor:not-allowed' : ''">
                  {{ __('pos.refund_credit') }}
                </button>
              </div>
            </div>

            <div class="field">
              <label for="reason">{{ __('pos.reason') }}</label>
              <input id="reason" type="text" x-model="reason" maxlength="255">
            </div>

            <template x-if="error">
              <p class="pos-alert" x-text="error"></p>
            </template>

            <div class="modal-actions">
              <button type="button" class="btn-confirm"
                      :disabled="refundTotal <= 0 || saving"
                      @click="submit()">
                <span x-text="saving ? '{{ __('pos.saving') }}' : '{{ __('pos.confirm_return') }}'"></span>
              </button>
              <a class="btn-cancel" href="{{ route('pos.index') }}"
                 style="text-align:center;line-height:26px;text-decoration:none">{{ __('pos.cancel') }}</a>
            </div>
          @endif

          {{-- Completed --}}
          <template x-if="completed">
            <div class="modal-scrim">
              <div class="modal" style="text-align:center">
                <h2>{{ __('pos.return_done') }}</h2>
                <p style="font-size:20px;font-weight:700" x-text="completed.receipt_number"></p>
                <div class="change-due">
                  <div>{{ __('pos.refunded_amount') }}</div>
                  <div class="amount" x-text="completed.refunded.toFixed(2) + ' ج.م'"></div>
                </div>
                <div class="modal-actions">
                  <button type="button" class="btn-confirm" @click="printReceipt()">{{ __('pos.print_receipt') }}</button>
                  <a class="btn-cancel" href="{{ route('pos.returns') }}"
                     style="text-align:center;line-height:26px;text-decoration:none">{{ __('pos.return_title') }}</a>
                </div>
              </div>
            </div>
          </template>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
