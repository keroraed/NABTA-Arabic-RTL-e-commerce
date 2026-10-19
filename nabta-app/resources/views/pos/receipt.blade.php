{{-- Printable receipt. Sized for an 80mm thermal roll; falls back to the
     browser print dialog, which is what the shop uses until the bridge agent
     with direct ESC/POS printing lands.

     A return is stored with negated amounts so the ledgers net out with a plain
     SUM. On paper that would just look like a confusing receipt full of minus
     signs, so this template flips the sign back and says plainly that it is a
     return, against which original receipt. --}}
@extends('layouts.pos')

@php($isReturn = $sale->type === 'return')

@section('title', ($isReturn ? __('pos.return_receipt') : __('pos.receipt')).' '.$sale->receipt_number)

@section('body')
<div class="no-print" style="padding:16px;text-align:center">
  <button onclick="window.print()" style="padding:14px 30px;border:0;border-radius:5px;font-size:16px;font-weight:700;cursor:pointer;background:#176B3A;color:#fff">{{ __('pos.print') }}</button>
  <a href="{{ route('pos.index') }}" style="margin-inline-start:12px">{{ __('pos.back_to_till') }}</a>
</div>

<div class="receipt">
  <div class="centre">
    <img src="{{ asset('assets/logo.webp') }}" alt="نبته" style="height:44px;width:auto">
    <div style="font-weight:700;font-size:15px;margin-top:4px">نبته</div>
    <div>{{ \App\Models\Setting::get('store_address_ar', '') }}</div>
    <div>{{ \App\Models\Setting::get('store_phone', '') }}</div>
  </div>

  @if ($isReturn)
    <hr>
    <div class="centre" style="font-weight:700;font-size:15px;border:1.5px solid #000;padding:4px 0">
      {{ __('pos.return_receipt') }}
    </div>
  @endif

  <hr>

  <table>
    <tr><td>{{ __('pos.invoice') }}</td><td class="num">{{ $sale->receipt_number }}</td></tr>
    @if ($isReturn && $sale->original_sale_id)
      <tr>
        <td>{{ __('pos.original_sale') }}</td>
        <td class="num">{{ \App\Models\PosSale::find($sale->original_sale_id)?->receipt_number }}</td>
      </tr>
    @endif
    <tr><td>{{ __('pos.date') }}</td><td class="num">{{ $sale->sold_at->format('Y/m/d H:i') }}</td></tr>
    <tr><td>{{ __('pos.terminal') }}</td><td class="num">{{ $sale->terminal?->code }}</td></tr>
    @if ($sale->soldBy)
      <tr><td>{{ __('pos.seller') }}</td><td class="num">{{ $sale->soldBy->name }}</td></tr>
    @endif
    @if ($sale->customer)
      <tr><td>{{ __('pos.customer') }}</td><td class="num">{{ $sale->customer->name }}</td></tr>
    @endif
  </table>

  <hr>

  <table>
    @foreach ($sale->items as $item)
      @php($qty = abs((float) ($item->weight_kg ?? $item->quantity)))
      <tr>
        <td colspan="2" style="font-weight:600">{{ $item->product_name_snapshot }}</td>
      </tr>
      <tr>
        <td>
          @if ($item->weight_kg !== null)
            {{ rtrim(rtrim(number_format($qty, 3), '0'), '.') }} كجم ×
            {{ number_format($item->unit_price_snapshot, 2) }} {{ __('pos.per_kg') }}
          @else
            {{ rtrim(rtrim(number_format($qty, 3), '0'), '.') }} ×
            {{ number_format($item->unit_price_snapshot, 2) }}
          @endif
        </td>
        <td class="num">{{ number_format(abs((float) $item->line_total), 2) }}</td>
      </tr>
    @endforeach
  </table>

  <hr>

  <table>
    <tr><td>{{ __('pos.subtotal') }}</td><td class="num">{{ number_format(abs((float) $sale->subtotal), 2) }}</td></tr>
    @if (abs((float) $sale->discount) > 0)
      <tr><td>{{ __('pos.discount') }}</td><td class="num">- {{ number_format(abs((float) $sale->discount), 2) }}</td></tr>
    @endif
    <tr class="grand">
      <td>{{ $isReturn ? __('pos.refunded_amount') : __('pos.total') }}</td>
      <td class="num">{{ number_format(abs((float) $sale->total), 2) }} ج.م</td>
    </tr>
    <tr><td>{{ __('pos.payment_method') }}</td><td class="num">{{ __($sale->paymentLabel()) }}</td></tr>

    @if (abs((float) $sale->paid_cash) > 0)
      <tr>
        <td>{{ $isReturn ? __('pos.refund_cash') : __('pos.cash_paid') }}</td>
        <td class="num">{{ number_format(abs((float) $sale->paid_cash), 2) }}</td>
      </tr>
    @endif
    @if (abs((float) $sale->paid_on_credit) > 0)
      <tr>
        <td>{{ $isReturn ? __('pos.refund_credit') : __('pos.on_account') }}</td>
        <td class="num">{{ number_format(abs((float) $sale->paid_on_credit), 2) }}</td>
      </tr>
    @endif
    @if (! $isReturn && $sale->change_given > 0)
      <tr><td>{{ __('pos.remaining') }}</td><td class="num">{{ number_format($sale->change_given, 2) }}</td></tr>
    @endif
  </table>

  <hr>
  <div class="centre">{{ __('pos.thanks') }}</div>
</div>
@endsection
