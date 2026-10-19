{{-- Ported from order-confirmation.html. That page read the order back out of
     sessionStorage; this one reads a real order, and the route only serves it to
     the session that placed it. --}}
@extends('layouts.storefront')

@section('title', 'تم استلام طلبك | نبته NABTA')

@push('head')
  <meta name="robots" content="noindex">
@endpush

@section('body')

  @include('partials.chrome-header', ['active' => 'shop'])

  <main style="padding-top:76px">
    <section style="max-width:1080px;margin:0 auto;padding:clamp(34px,6vw,64px) clamp(16px,4vw,44px) clamp(60px,8vw,100px)">

      <div style="text-align:center;margin-bottom:clamp(32px,5vw,52px)">
        <div class="tick-wrap" style="width:72px;height:72px;margin:0 auto 20px;border-radius:50%;background:rgba(23,107,58,.1);display:grid;place-items:center">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#176B3A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path class="tick-path" d="m5 13 4 4 10-10"/>
          </svg>
        </div>
        <h1 style="margin:0 0 10px;font-size:clamp(1.7rem,3.6vw,2.6rem);font-weight:700;letter-spacing:-.02em">تم استلام طلبك بنجاح</h1>
        <p style="margin:0;font-size:15.5px;line-height:1.85;color:#3A483D;font-weight:300">سنتواصل معك هاتفياً خلال ٢٤ ساعة لتأكيد الطلب وموعد التسليم.</p>
        <p style="margin:18px 0 0;font-size:14px;color:#6B4630">رقم الطلب</p>
        <p style="margin:4px 0 0;font-size:clamp(20px,3vw,26px);font-weight:700;color:#176B3A;letter-spacing:.04em">{{ $order->order_number }}</p>
      </div>

      <div class="oc-grid" style="display:grid;grid-template-columns:1.15fr .85fr;gap:clamp(20px,3vw,36px);align-items:start">

        <div class="card" style="padding:clamp(20px,3vw,30px)">
          <h2 style="margin:0 0 18px;font-size:17px;font-weight:700">تفاصيل الطلب</h2>
          <div style="display:flex;flex-direction:column;gap:16px">
            @foreach ($order->items as $item)
              <div style="display:flex;gap:12px;align-items:flex-start">
                <div style="position:relative;flex:0 0 62px;width:62px;height:62px;background:#F5EBD8;border-radius:3px">
                  @if ($item->product?->primary_image_url)
                    <img src="{{ $item->product->primary_image_url }}" alt="{{ $item->name_ar }}" style="position:absolute;inset:0;margin:auto;width:78%;height:78%;object-fit:contain">
                  @endif
                </div>
                <div style="flex:1;min-width:0">
                  <p style="margin:0 0 3px;font-size:14px;font-weight:600;line-height:1.5">{{ $item->name_ar }}</p>
                  <p style="margin:0;font-size:12.5px;color:#6B4630">{{ number_format($item->unit_price, 0) }} ج.م × {{ $item->qty }}</p>
                </div>
                <p style="margin:0;font-size:14.5px;font-weight:700;white-space:nowrap">{{ number_format($item->line_total, 0) }} ج.م</p>
              </div>
            @endforeach
          </div>

          <div style="border-top:1px solid rgba(14,42,27,.1);margin-top:20px;padding-top:16px;display:flex;flex-direction:column;gap:11px;font-size:14.5px">
            <div style="display:flex;justify-content:space-between;gap:12px">
              <span style="color:#3A483D">المجموع الفرعي</span><span style="font-weight:600">{{ number_format($order->subtotal, 0) }} ج.م</span>
            </div>
            <div style="display:flex;justify-content:space-between;gap:12px">
              <span style="color:#3A483D">الشحن</span>
              <span style="font-weight:600">{{ (float) $order->shipping_fee === 0.0 ? 'مجاني' : number_format($order->shipping_fee, 0).' ج.م' }}</span>
            </div>
          </div>
          <div style="border-top:2px solid rgba(14,42,27,.12);margin-top:16px;padding-top:16px;display:flex;justify-content:space-between;align-items:baseline;gap:12px">
            <span style="font-size:16px;font-weight:700">الإجمالي</span>
            <span style="font-size:22px;font-weight:700;color:#176B3A">{{ number_format($order->total, 0) }} ج.م</span>
          </div>
          <p style="margin:14px 0 0;font-size:13px;color:#6B4630;line-height:1.75">طريقة الدفع: <strong style="font-weight:600">الدفع عند الاستلام</strong></p>
        </div>

        <div class="card" style="padding:clamp(20px,3vw,30px)">
          <h2 style="margin:0 0 18px;font-size:17px;font-weight:700">بيانات التوصيل</h2>
          <dl style="margin:0;display:flex;flex-direction:column;gap:14px;font-size:14px;line-height:1.75">
            <div>
              <dt style="color:#6B4630;font-size:12.5px;margin-bottom:2px">الاسم</dt>
              <dd style="margin:0;font-weight:600">{{ $order->customer_name }}</dd>
            </div>
            <div>
              <dt style="color:#6B4630;font-size:12.5px;margin-bottom:2px">رقم الموبايل</dt>
              <dd style="margin:0;font-weight:600;text-align:start" dir="ltr">{{ $order->customer_phone }}</dd>
            </div>
            @if ($order->customer_alt_phone)
              <div>
                <dt style="color:#6B4630;font-size:12.5px;margin-bottom:2px">رقم بديل</dt>
                <dd style="margin:0;font-weight:600" dir="ltr">{{ $order->customer_alt_phone }}</dd>
              </div>
            @endif
            <div>
              <dt style="color:#6B4630;font-size:12.5px;margin-bottom:2px">العنوان</dt>
              <dd style="margin:0">{{ $order->governorate_name_snapshot }} — {{ $order->city }}<br>{{ $order->address_line }}</dd>
            </div>
            @if ($order->landmark)
              <div>
                <dt style="color:#6B4630;font-size:12.5px;margin-bottom:2px">علامة مميزة</dt>
                <dd style="margin:0">{{ $order->landmark }}</dd>
              </div>
            @endif
            @if ($order->notes)
              <div>
                <dt style="color:#6B4630;font-size:12.5px;margin-bottom:2px">ملاحظات</dt>
                <dd style="margin:0">{{ $order->notes }}</dd>
              </div>
            @endif
          </dl>

          <a href="{{ route('shop') }}" style="display:block;text-align:center;margin-top:24px;background:#0E2A1B;color:#F5EBD8;font-size:15px;font-weight:600;padding:15px;border-radius:2px">متابعة التسوق</a>
        </div>
      </div>
    </section>
  </main>

  @include('partials.overlays')
  @include('partials.mobile-menu')

  @include('partials.chrome-footer')

@endsection
