{{-- Ported from categories.html. The eight cards use the same shelf-breakout
     motion as the home page tiles (nabta.js drives both from [data-tile]), on a
     taller card. Art direction per card lives in config/nabta.php; the copy lives
     on the category row so the client can edit it. --}}
@extends('layouts.storefront')

@section('title', 'جميع الأقسام الطبيعية | نبته NABTA')
@section('description', 'استكشف رفوف نبته الكاملة — أعشاب، توابل، مكسرات، بذور، عسل، صابون، خلطات وعناية طبيعية.')

@push('head')
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
@endpush

@section('body')

  @include('partials.chrome-header', ['active' => 'categories'])

  <div data-motion>

    <section style="position:relative;background:#F5EBD8;padding:clamp(120px,16vw,170px) clamp(16px,4vw,60px) clamp(50px,7vw,90px);overflow:hidden">
      <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(107,70,48,.1) .5px,transparent .5px);background-size:3px 3px;pointer-events:none"></div>
      <div style="position:absolute;top:10%;inset-inline-end:5%;width:clamp(120px,14vw,220px);opacity:.18;pointer-events:none;animation:nbSway 12s ease-in-out infinite">
        <svg viewBox="0 0 200 260" fill="none"><path d="M100 258C100 258 96 150 100 2" stroke="#B08D57" stroke-width="1.4"/><path d="M100 60c0 0 34-24 62-8-8 30-40 34-62 8Z" stroke="#0E2A1B" stroke-width="1.2"/><path d="M100 108c0 0-34-24-62-8 8 30 40 34 62 8Z" stroke="#0E2A1B" stroke-width="1.2"/><path d="M100 156c0 0 30-22 56-7-7 27-36 31-56 7Z" stroke="#0E2A1B" stroke-width="1.2"/></svg>
      </div>

      <div style="position:relative;max-width:1560px;margin:0 auto">
        <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#6B4630;margin-bottom:18px">
          <a href="{{ route('home') }}" style="opacity:.8;color:inherit">الرئيسية</a>
          <span>/</span>
          <span style="color:#176B3A;font-weight:600">الأقسام الطبيعية</span>
        </div>

        <div style="max-width:760px">
          <p style="margin:0 0 12px;font-size:clamp(11px,1.1vw,13px);letter-spacing:.34em;color:#6B4630;font-weight:500">خارطة المنتجات</p>
          <h1 style="margin:0;font-size:clamp(2.2rem,5.6vw,4.4rem);line-height:1.15;font-weight:700;color:#0E2A1B;letter-spacing:-.02em">جميع الأقسام الطبيعية</h1>
          <p style="margin:20px 0 0;font-size:clamp(15px,1.4vw,19px);line-height:1.85;font-weight:300;color:#3A483D;text-wrap:pretty">استكشف رفوف نبته الكاملة. {{ $categories->count() }} أقسام رئيسية تجمع أجود أنواع الأعشاب، التوابل، المكسرات، البذور، العسل، الخلطات، والعناية الطبيعية المنتقاة بعناية.</p>
        </div>

        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:clamp(28px,4vw,44px)">
          <span style="font-size:13px;font-weight:600;color:#6B4630">الأقسام ({{ $categories->count() }}):</span>
          @foreach ($categories as $c)
            <a href="{{ route('shop', ['cat' => $c->slug]) }}" style="background:rgba(23,107,58,.12);color:#176B3A;font-size:13px;font-weight:600;padding:6px 16px;border-radius:9999px">{{ $c->name_ar }}</a>
          @endforeach
        </div>
      </div>
    </section>

    <main style="max-width:1560px;margin:0 auto;padding:clamp(60px,8vw,110px) clamp(16px,4vw,60px)">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:clamp(20px,2.5vw,36px)">

        @foreach ($categories as $c)
          @php($art = $cardArt($c))
          <a href="{{ route('shop', ['cat' => $c->slug]) }}" class="cat-card" data-tile="{{ $art['hero'] ?? $c->slug }}" onmouseenter="NB.tileEnter(event)" onmouseleave="NB.tileLeave(event)" onmousemove="NB.tileMove(event)" style="position:relative;display:flex;flex-direction:column;border-radius:240px 240px 16px 16px;background:transparent;overflow:visible;box-shadow:0 12px 30px rgba(14,42,27,.05);transition:box-shadow .4s ease, border-color .4s ease;text-decoration:none">
            <div data-tile-shelf style="position:absolute;inset:0;border-radius:240px 240px 16px 16px;background:#FCFAF5;border:1px solid rgba(14,42,27,.12);overflow:hidden;pointer-events:none">
              <div data-tile-bg style="position:absolute;inset:0;background:radial-gradient(90% 70% at 50% 100%,{{ $art['glow'] }},transparent);opacity:0"></div>
            </div>
            <div style="position:relative;height:320px;overflow:visible;pointer-events:none;z-index:3">
              @if ($art['normal_src'])
                <img data-tile-normal src="{{ $art['normal_src'] }}" alt="{{ $c->title_ar ?? $c->name_ar }}" loading="lazy" style="position:absolute;{{ $art['normal'] }};inset-inline:0;margin-inline:auto;height:auto;object-fit:contain;filter:drop-shadow(0 20px 28px rgba(14,42,27,.2));will-change:transform,opacity"/>
              @endif
              <div data-tile-float style="position:absolute;inset:0;pointer-events:none;will-change:transform">
                @if ($art['opened_src'])
                  <img data-tile-opened src="{{ $art['opened_src'] }}" alt="{{ $c->title_ar ?? $c->name_ar }} مفتوحة" loading="lazy" style="position:absolute;{{ $art['opened'] }};inset-inline:0;margin-inline:auto;height:auto;object-fit:contain;filter:drop-shadow(0 28px 40px rgba(14,42,27,.28));opacity:0;will-change:transform,opacity"/>
                @endif
              </div>
            </div>
            <div style="position:relative;z-index:5;padding:26px 26px 28px;display:flex;flex-direction:column;flex:1;background:#FCFAF5;border-top:1px solid rgba(14,42,27,.06);border-radius:0 0 16px 16px">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px">
                <h3 style="margin:0;font-size:22px;font-weight:600;color:#0E2A1B">{{ $c->title_ar ?? $c->name_ar }}</h3>
                @if ($c->badge_ar)
                  <span style="font-family:'Jost',sans-serif;font-size:12px;font-weight:600;color:#176B3A;background:rgba(23,107,58,.1);padding:3px 10px;border-radius:9999px">{{ $c->badge_ar }}</span>
                @endif
              </div>
              <p style="margin:0 0 20px;font-size:14px;line-height:1.75;color:#4A584D;flex:1">{{ $c->description_ar }}</p>
              <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:14px;border-top:1px dashed rgba(14,42,27,.12)">
                <span style="font-size:14px;font-weight:600;color:#176B3A">{{ $c->products_count > 0 ? 'استكشف منتجات القسم' : 'قريباً' }}</span>
                <svg data-tile-arrow width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#176B3A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="opacity:.6"><path d="M20 12H4"/><path d="m10 6-6 6 6 6"/></svg>
              </div>
            </div>
          </a>
        @endforeach

      </div>
    </main>
  </div>

  @include('partials.overlays')
  @include('partials.mobile-menu')

  @include('partials.chrome-footer')

@endsection
