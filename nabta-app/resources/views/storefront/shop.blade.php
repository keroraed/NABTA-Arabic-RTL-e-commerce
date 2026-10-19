{{-- Ported from shop.html. The category chips filtered client-side there; here
     they are real links, so every filtered view has its own URL and can be
     linked, shared and indexed.

     Three chip rows: top-level categories, the subcategories of whichever one
     is selected, and brands. Each link keeps the other filters it does not
     change, so picking a brand inside a category narrows rather than resets. --}}
@php
    $brandParam = $activeBrand ? ['brand' => $activeBrand->slug] : [];
    $catParam = $activeCategory ? ['cat' => $activeCategory->slug] : [];
    $heading = $activeCategory?->name_ar ?? $activeBrand?->name_ar ?? 'كل المنتجات';
@endphp
@extends('layouts.storefront')

@section('title', $heading.' | نبته NABTA')
@section('description', 'تصفّح كل منتجات نبته الطبيعية — أعشاب، توابل، مكسرات، بذور، عسل وعناية طبيعية. الدفع عند الاستلام في جميع المحافظات.')

@section('body')

  @include('partials.chrome-header', ['active' => 'shop'])

  <section style="position:relative;background:#F5EBD8;padding:clamp(120px,16vw,170px) clamp(16px,4vw,60px) clamp(40px,6vw,70px);overflow:hidden">
    <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(107,70,48,.1) .5px,transparent .5px);background-size:3px 3px;pointer-events:none"></div>
    <div style="position:relative;max-width:1560px;margin:0 auto">
      <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#6B4630;margin-bottom:18px">
        <a href="{{ route('home') }}" style="opacity:.8;color:inherit">الرئيسية</a><span>/</span>
        @if ($activeCategory)
          <a href="{{ route('shop') }}" style="opacity:.8;color:inherit">كل المنتجات</a><span>/</span>
          @if ($activeRoot && $activeRoot->isNot($activeCategory))
            <a href="{{ route('shop', ['cat' => $activeRoot->slug] + $brandParam) }}" style="opacity:.8;color:inherit">{{ $activeRoot->name_ar }}</a><span>/</span>
          @endif
          <span style="color:#176B3A;font-weight:600">{{ $activeCategory->name_ar }}</span>
        @elseif ($activeBrand)
          <a href="{{ route('shop') }}" style="opacity:.8;color:inherit">كل المنتجات</a><span>/</span>
          <span style="color:#176B3A;font-weight:600">{{ $activeBrand->name_ar }}</span>
        @else
          <span style="color:#176B3A;font-weight:600">كل المنتجات</span>
        @endif
      </div>
      <h1 style="margin:0;font-size:clamp(2.1rem,5.2vw,3.8rem);line-height:1.15;font-weight:700;letter-spacing:-.02em">{{ $heading }}</h1>
      <p style="margin:18px 0 0;font-size:clamp(15px,1.4vw,18px);line-height:1.85;font-weight:300;color:#3A483D;max-width:60ch">منتجات طبيعية منتقاة ومعبّأة يدوياً. اطلب الآن وادفع عند الاستلام.</p>
    </div>
  </section>

  <main style="max-width:1560px;margin:0 auto;padding:clamp(32px,5vw,56px) clamp(16px,4vw,60px) clamp(60px,8vw,110px)">

    @foreach (['status', 'cart_error'] as $flash)
      @if (session($flash))
        <p role="status" style="margin:0 0 24px;padding:14px 16px;background:{{ $flash === 'cart_error' ? '#FFF2F2' : '#F5EBD8' }};border:1px solid {{ $flash === 'cart_error' ? '#E9C7C7' : 'rgba(107,70,48,.2)' }};border-radius:3px;font-size:14px;color:{{ $flash === 'cart_error' ? '#B04A4A' : '#2C3E30' }};line-height:1.7">{{ session($flash) }}</p>
      @endif
    @endforeach

    <div class="shop-filters" style="margin-bottom:clamp(28px,4vw,44px)">
      <nav class="chip-row" aria-label="الأقسام">
        <a class="chip" href="{{ route('shop', $brandParam) }}" aria-pressed="{{ $activeCategory ? 'false' : 'true' }}">كل الأقسام</a>
        @foreach ($categories as $c)
          <a class="chip" href="{{ route('shop', ['cat' => $c->slug] + $brandParam) }}" aria-pressed="{{ $activeRoot?->id === $c->id ? 'true' : 'false' }}">{{ $c->name_ar }}</a>
        @endforeach
      </nav>

      @if ($subcategories->isNotEmpty())
        <nav class="chip-row chip-row--sub" aria-label="الأقسام الفرعية">
          <a class="chip" href="{{ route('shop', ['cat' => $activeRoot->slug] + $brandParam) }}" aria-pressed="{{ $activeCategory?->is($activeRoot) ? 'true' : 'false' }}">كل {{ $activeRoot->name_ar }}</a>
          @foreach ($subcategories as $sub)
            <a class="chip" href="{{ route('shop', ['cat' => $sub->slug] + $brandParam) }}" aria-pressed="{{ $activeCategory?->id === $sub->id ? 'true' : 'false' }}">{{ $sub->name_ar }}</a>
          @endforeach
        </nav>
      @endif

      @if ($brands->isNotEmpty())
        <nav class="chip-row" aria-label="العلامات التجارية">
          <span class="chip-row__label">العلامة:</span>
          <a class="chip" href="{{ route('shop', $catParam) }}" aria-pressed="{{ $activeBrand ? 'false' : 'true' }}">الكل</a>
          @foreach ($brands as $b)
            <a class="chip" href="{{ route('shop', $catParam + ['brand' => $b->slug]) }}" aria-pressed="{{ $activeBrand?->id === $b->id ? 'true' : 'false' }}">{{ $b->name_ar }}</a>
          @endforeach
        </nav>
      @endif
    </div>

    @if ($products->isNotEmpty())
      <p style="margin:0 0 20px;font-size:14px;color:#6B4630">{{ $products->count() }} منتج</p>
    @endif

    <div class="shop-grid">
      @foreach ($products as $p)
        @php($out = $p->isOutOfStock())
        <article class="p-card">
          <a href="{{ route('products.show', $p) }}" style="display:block;position:relative;background:#F5EBD8;aspect-ratio:1/1;overflow:hidden">
            @if ($out)
              <span style="position:absolute;top:12px;inset-inline-start:12px;z-index:2;background:rgba(14,42,27,.9);color:#F5EBD8;font-size:11px;font-weight:700;padding:5px 12px;border-radius:9999px">نفدت الكمية</span>
            @elseif ($p->isLowStock())
              <span style="position:absolute;top:12px;inset-inline-start:12px;z-index:2;background:#B08D57;color:#FCFAF5;font-size:11px;font-weight:700;padding:5px 12px;border-radius:9999px">آخر {{ $p->stock }} قطع</span>
            @endif
            <img class="p-img" src="{{ $p->primary_image_url }}" alt="{{ $p->name_ar }}" loading="lazy" decoding="async" style="position:absolute;inset:0;margin:auto;width:80%;height:80%;object-fit:contain;filter:drop-shadow(0 14px 20px rgba(14,42,27,.16)){{ $out ? ';opacity:.45' : '' }}">
          </a>
          <div style="padding:18px 18px 20px;display:flex;flex-direction:column;gap:10px;flex:1">
            <p style="margin:0;font-size:12px;letter-spacing:.12em;color:#6B4630;font-weight:500">{{ $p->category?->name_ar }}</p>
            <a href="{{ route('products.show', $p) }}" style="color:inherit"><h2 style="margin:0;font-size:17px;font-weight:600;line-height:1.5">{{ $p->name_ar }}</h2></a>
            <p style="margin:0;font-size:13px;line-height:1.75;color:#4a5a4e;font-weight:300;flex:1">{{ $p->short_desc_ar }}</p>
            <p style="margin:4px 0 8px;font-size:19px;font-weight:700;color:#176B3A">{{ number_format($p->price, 0) }} ج.م</p>
            <button class="btn-add" type="button" data-id="{{ $p->id }}" @disabled($out) onclick="NB.onAdd(event)">{{ $out ? 'غير متاح' : 'أضف إلى السلة' }}</button>
          </div>
        </article>
      @endforeach
    </div>

    @if ($products->isEmpty())
      <p style="margin:60px 0;text-align:center;font-size:16px;color:#6B4630">لا توجد منتجات تطابق هذا الاختيار حالياً.</p>
    @endif
  </main>

  @include('partials.overlays')
  @include('partials.mobile-menu')

  @include('partials.chrome-footer')
  @include('partials.toast')

@endsection
