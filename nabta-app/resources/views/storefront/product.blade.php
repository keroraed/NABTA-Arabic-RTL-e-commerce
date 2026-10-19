{{-- Ported from product.html, which rendered this whole page with JavaScript
     from a ?id= query string. It is server-rendered now with its own URL, title,
     meta description, canonical and Product JSON-LD (HANDOFF.md 2.5). --}}
@extends('layouts.storefront')

@section('title', $product->name_ar.' | نبته NABTA')
{{-- Never pass null as section content: Blade's startSection() reads a null
     second argument as "capture until endsection" and leaks an open output
     buffer for the rest of the request. A product with no image or no short
     description is perfectly legal, so both are coalesced. --}}
@section('description', $product->short_desc_ar ?: 'منتجات نبته الطبيعية — الدفع عند الاستلام.')
@section('og_type', 'product')
@section('og_image', $product->primary_image_url ?: asset('assets/logo.webp'))

@push('head')
  {{-- Built in a PHP block rather than an inline json directive: Blade reads the
       leading "at" of the schema.org "context" / "type" keys as directives and
       mangles the array literal. --}}
  @php
      $jsonLd = json_encode([
          '@context' => 'https://schema.org',
          '@type' => 'Product',
          'name' => $product->name_ar,
          'description' => $product->short_desc_ar,
          'sku' => $product->sku,
          'image' => $product->images->map(fn ($i) => $i->url)->all(),
          'category' => $product->category?->name_ar,
          'offers' => [
              '@type' => 'Offer',
              'url' => route('products.show', $product),
              'price' => (float) $product->price,
              'priceCurrency' => $product->currency,
              'availability' => $product->isOutOfStock()
                  ? 'https://schema.org/OutOfStock'
                  : 'https://schema.org/InStock',
          ],
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
  @endphp
  <script type="application/ld+json">{!! $jsonLd !!}</script>
@endpush

@section('body')

  @include('partials.chrome-header', ['active' => 'shop'])

  <main style="padding-top:76px" x-data="{ qty: 1, stock: {{ $product->stock }}, image: @js($product->primary_image_url), active: 0, open: null }">

    <section style="max-width:1560px;margin:0 auto;padding:clamp(28px,4vw,44px) clamp(16px,4vw,60px) 0">
      <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#6B4630;flex-wrap:wrap">
        <a href="{{ route('home') }}" style="opacity:.8;color:inherit">الرئيسية</a><span>/</span>
        <a href="{{ route('shop') }}" style="opacity:.8;color:inherit">المنتجات</a><span>/</span>
        <a href="{{ route('shop', ['cat' => $product->category?->slug]) }}" style="opacity:.8;color:inherit">{{ $product->category?->name_ar }}</a><span>/</span>
        <span style="color:#176B3A;font-weight:600">{{ $product->name_ar }}</span>
      </div>
    </section>

    <section style="max-width:1560px;margin:0 auto;padding:clamp(28px,4vw,50px) clamp(16px,4vw,60px) clamp(50px,7vw,90px)">
      <div class="pdp-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(28px,4vw,64px);align-items:start">

        <div>
          <div style="position:relative;background:#F5EBD8;border-radius:4px;aspect-ratio:1/1;overflow:hidden">
            @if ($product->isOutOfStock())
              <span style="position:absolute;top:18px;inset-inline-start:18px;z-index:2;background:rgba(14,42,27,.9);color:#F5EBD8;font-size:12px;font-weight:700;padding:7px 16px;border-radius:9999px">نفدت الكمية</span>
            @endif
            <img :src="image" alt="{{ $product->name_ar }}" style="position:absolute;inset:0;margin:auto;width:82%;height:82%;object-fit:contain;filter:drop-shadow(0 22px 34px rgba(14,42,27,.2)){{ $product->isOutOfStock() ? ';opacity:.5' : '' }}">
          </div>
          @if ($product->images->count() > 1)
            <div style="display:flex;gap:12px;margin-top:14px">
              @foreach ($product->images as $i => $img)
                <button class="thumb" type="button" :aria-pressed="active === {{ $i }}" @click="active = {{ $i }}; image = @js($img->url)" aria-label="صورة {{ $i + 1 }}">
                  <img src="{{ $img->url }}" alt="" loading="lazy" style="width:100%;aspect-ratio:1/1;object-fit:contain;display:block">
                </button>
              @endforeach
            </div>
          @endif
        </div>

        <div>
          <p style="margin:0 0 12px;font-size:12px;letter-spacing:.22em;color:#6B4630;font-weight:500">{{ $product->category?->name_ar }}</p>
          <h1 style="margin:0;font-size:clamp(1.8rem,3.6vw,2.9rem);line-height:1.25;font-weight:700;letter-spacing:-.02em">{{ $product->name_ar }}</h1>
          <p style="margin:16px 0 0;font-size:clamp(1.5rem,2.6vw,2rem);font-weight:700;color:#176B3A">{{ number_format($product->price, 0) }} ج.م</p>
          <p style="margin:20px 0 0;font-size:15px;line-height:1.95;color:#3A483D;font-weight:300">{{ $product->description_ar }}</p>

          <div style="display:flex;align-items:center;gap:10px;margin:22px 0 0;font-size:13px;color:#6B4630">
            <span style="display:inline-flex;align-items:center;gap:6px">
              <span style="width:8px;height:8px;border-radius:50%;background:{{ $product->isOutOfStock() ? '#B04A4A' : '#176B3A' }};display:inline-block"></span>
              {{ $product->isOutOfStock() ? 'غير متوفر حالياً' : ($product->isLowStock() ? 'آخر '.$product->stock.' قطع' : 'متوفر') }}
            </span>
            @if ($product->weight_label)
              <span style="opacity:.4">·</span><span>{{ $product->weight_label }}</span>
            @endif
            <span style="opacity:.4">·</span><span>{{ $product->sku }}</span>
          </div>

          @unless ($product->isOutOfStock())
            <div style="display:flex;align-items:center;gap:14px;margin:26px 0 0;flex-wrap:wrap">
              <div style="display:flex;align-items:center;border:1px solid rgba(14,42,27,.2);border-radius:2px;overflow:hidden">
                <button class="qty-btn" type="button" @click="qty = Math.max(1, qty - 1)" :disabled="qty <= 1" aria-label="إنقاص">−</button>
                <span x-text="qty" style="min-width:52px;text-align:center;font-size:16px;font-weight:600">1</span>
                <button class="qty-btn" type="button" @click="qty = Math.min(stock, qty + 1)" :disabled="qty >= stock" aria-label="زيادة">+</button>
              </div>
              <span x-show="qty >= stock" x-cloak style="font-size:12px;color:#6B4630">وصلت للحد المتاح في المخزون</span>
            </div>
            <div style="display:flex;gap:12px;margin:22px 0 0;flex-wrap:wrap">
              <button class="btn-primary" type="button" data-id="{{ $product->id }}" :data-qty="qty" onclick="NB.onAdd(event)" style="flex:1;min-width:190px">أضف إلى السلة</button>
              <button class="btn-ghost" type="button" @click="NB.cart.add({{ $product->id }}, qty).then(() => window.location = @js(route('checkout')))" style="flex:1;min-width:190px">اشترِ الآن</button>
            </div>
          @endunless

          <div style="display:flex;align-items:center;gap:10px;margin:24px 0 0;padding:16px 18px;background:#F5EBD8;border-radius:3px">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#176B3A" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:0 0 auto"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8Z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <p style="margin:0;font-size:13.5px;line-height:1.7;color:#2C3E30"><strong style="font-weight:600">الدفع عند الاستلام</strong> — تدفع للمندوب وقت التسليم. الشحن لكل المحافظات.</p>
          </div>

          <div style="margin-top:26px">
            @foreach ($panels as $i => [$heading, $panelBody])
              <div style="border-top:1px solid rgba(14,42,27,.1)">
                <button class="acc-head" type="button" @click="open = open === {{ $i }} ? null : {{ $i }}" :aria-expanded="open === {{ $i }} ? 'true' : 'false'">{{ $heading }}
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#176B3A" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="flex:0 0 auto;transition:transform .3s ease" :style="open === {{ $i }} ? 'transform:rotate(45deg)' : ''"><path d="M12 5v14M5 12h14"/></svg>
                </button>
                <div x-show="open === {{ $i }}" x-cloak style="padding:0 0 20px;font-size:14px;line-height:1.95;color:#4a5a4e;font-weight:300">{{ $panelBody }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </section>

    @if ($related->isNotEmpty())
      <section style="background:#F5EBD8;padding:clamp(50px,7vw,90px) clamp(16px,4vw,60px)" x-data="{ scrollBy(dir) { $refs.track.scrollBy({ left: dir * $refs.track.clientWidth * 0.8, behavior: 'smooth' }); } }">
        <div style="max-width:1560px;margin:0 auto">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:20px;margin:0 0 clamp(24px,3vw,40px)">
            <h2 style="margin:0;font-size:clamp(1.4rem,2.8vw,2.1rem);font-weight:600">قد يعجبك أيضاً</h2>
            <div style="display:flex;align-items:center;gap:10px;flex:0 0 auto">
              <button type="button" class="rel-arrow" @click="scrollBy(1)" aria-label="السابق">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M4 12h16"/><path d="m14 6 6 6-6 6"/></svg>
              </button>
              <button type="button" class="rel-arrow" @click="scrollBy(-1)" aria-label="التالي">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M20 12H4"/><path d="m10 6-6 6 6 6"/></svg>
              </button>
            </div>
          </div>
          <div class="rel-track" x-ref="track">
            @foreach ($related as $r)
              <a class="rel-item" href="{{ route('products.show', $r) }}">
                <div style="position:relative;background:#F5EBD8;aspect-ratio:1/1">
                  <img src="{{ $r->primary_image_url }}" alt="{{ $r->name_ar }}" loading="lazy" style="position:absolute;inset:0;margin:auto;width:78%;height:78%;object-fit:contain">
                </div>
                <div style="padding:16px">
                  <p style="margin:0 0 6px;font-size:11.5px;letter-spacing:.12em;color:#6B4630">{{ $r->category?->name_ar }}</p>
                  <p style="margin:0 0 8px;font-size:15px;font-weight:600;line-height:1.5">{{ $r->name_ar }}</p>
                  <p style="margin:0;font-size:16px;font-weight:700;color:#176B3A">{{ number_format($r->price, 0) }} ج.م</p>
                </div>
              </a>
            @endforeach
          </div>
        </div>
      </section>
    @endif
  </main>

  @include('partials.overlays')
  @include('partials.mobile-menu')

  @include('partials.chrome-footer')
  @include('partials.toast')

@endsection
