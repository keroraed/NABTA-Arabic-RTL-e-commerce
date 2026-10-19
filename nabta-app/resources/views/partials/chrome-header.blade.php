{{--
  Shared header for the inner pages (shop, PDP, checkout, confirmation,
  categories). Ported from assets/js/nabta-chrome.js, which injected this with
  JavaScript. It is server-rendered now, which is what HANDOFF.md 2.5 asked for:
  a product page needs its nav in the HTML.

  Mobile behaves the same as the home page: the five nav links collapse into the
  hamburger and the full-screen menu overlay (partials.mobile-menu) rather than
  being squeezed into a phone-width bar. The cart and search icons open the same
  overlays the home page uses, so the chrome is identical everywhere.

  @param string $active  one of: home, shop, categories, story, contact
--}}
@php
    $nav = [
        ['label' => 'الرئيسية',   'href' => route('home'),               'key' => 'home'],
        ['label' => 'المنتجات',   'href' => route('shop'),               'key' => 'shop'],
        ['label' => 'الأقسام',    'href' => route('categories'),         'key' => 'categories'],
        ['label' => 'من نحن',     'href' => route('home').'#story',      'key' => 'story'],
        ['label' => 'تواصل معنا', 'href' => route('home').'#contact',    'key' => 'contact'],
    ];
    $active ??= '';
@endphp

<header style="position:fixed;inset-inline:0;top:0;z-index:90">
  <div style="position:absolute;inset:0;background:rgba(252,250,245,.94);backdrop-filter:blur(16px);border-bottom:1px solid rgba(14,42,27,.08);box-shadow:0 4px 24px rgba(14,42,27,.04)"></div>
  <div style="position:relative;max-width:1560px;margin:0 auto;padding:0 clamp(16px,3vw,44px);height:76px;display:flex;align-items:center;justify-content:space-between;gap:20px">

    <a href="{{ route('home') }}" style="display:flex;align-items:center;gap:12px;flex:0 0 auto">
      <img src="{{ asset('assets/logo.webp') }}" alt="نبته NABTA" width="130" height="86" style="height:clamp(38px,4.2vw,50px);width:auto;object-fit:contain;display:block">
    </a>

    <div class="nb-desk">
      <nav aria-label="التنقل الرئيسي" style="display:flex;align-items:center;gap:clamp(14px,2.2vw,36px)">
        @foreach ($nav as $item)
          @php($on = $item['key'] === $active)
          <a href="{{ $item['href'] }}"
             @if ($on) aria-current="page" @endif
             class="{{ $on ? '' : 'nb-navlink' }}"
             style="font-size:15px;font-weight:{{ $on ? '600' : '500' }};color:{{ $on ? '#0E2A1B' : '#2C3E30' }};padding:8px 2px;white-space:nowrap{{ $on ? ';border-bottom:2.5px solid #176B3A' : ';transition:color .2s' }}">{{ $item['label'] }}</a>
        @endforeach
      </nav>
    </div>

    <div style="display:flex;align-items:center;gap:8px;flex:0 0 auto">
      <button class="nbh-3" type="button" onclick="NB.openSearch(event)" aria-label="بحث" style="width:42px;height:42px;display:grid;place-items:center;background:none;border:0;cursor:pointer;border-radius:50%;transition:background .2s">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4.3-4.3"/></svg>
      </button>

      <button class="nbh-3" type="button" onclick="NB.openCart(event)" aria-label="سلة المشتريات" style="position:relative;width:42px;height:42px;display:grid;place-items:center;background:none;border:0;cursor:pointer;border-radius:50%;transition:background .2s">
        <svg data-cart-icon width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <span data-cart-badge x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count" style="position:absolute;top:2px;inset-inline-start:2px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#176B3A;color:#FCFAF5;font-size:11px;font-weight:700;display:grid;place-items:center;box-sizing:border-box"></span>
      </button>

      <div class="nb-mob">
        <button type="button" onclick="NB.openMenu(event)" aria-label="القائمة" style="width:42px;height:42px;display:grid;place-items:center;background:none;border:0;cursor:pointer;border-radius:50%">
          <span style="display:block;width:22px">
            <span style="display:block;height:1.8px;background:#0E2A1B;margin-bottom:6px;border-radius:1px"></span>
            <span style="display:block;height:1.8px;background:#0E2A1B;width:15px;border-radius:1px"></span>
          </span>
        </button>
      </div>
    </div>
  </div>
</header>
