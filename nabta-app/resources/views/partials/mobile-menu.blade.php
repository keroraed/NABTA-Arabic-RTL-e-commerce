{{--
  Full-screen mobile menu, lifted from the home page's Design Canvas layout so
  every page gets the same one instead of the inner pages cramming five nav links
  into a phone-width bar.

  @param array $links  [['href' => ..., 'label' => ..., 'nav' => ?string], ...]
                       `nav` is set only for same-page anchors on the home page,
                       where NB.navTo smooth-scrolls instead of navigating.
--}}
@php
    $links ??= [
        ['href' => route('home'),            'label' => 'الرئيسية'],
        ['href' => route('shop'),            'label' => 'المنتجات'],
        ['href' => route('categories'),      'label' => 'الأقسام'],
        ['href' => route('home').'#story',   'label' => 'من نحن'],
        ['href' => route('home').'#contact', 'label' => 'تواصل معنا'],
    ];
@endphp

<div data-menu role="dialog" aria-modal="true" aria-label="القائمة" aria-hidden="true" style="position:fixed;inset:0;z-index:150;pointer-events:none;visibility:hidden;opacity:0;background:#0E2A1B">
  <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.05) .5px,transparent .5px);background-size:3px 3px"></div>
  <div style="position:relative;height:100%;display:flex;flex-direction:column;padding:22px clamp(20px,6vw,40px) 40px;box-sizing:border-box">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <span style="font-size:24px;font-weight:600;color:#F5EBD8">نبته</span>
      <button type="button" onclick="NB.closeMenu(event)" aria-label="إغلاق القائمة" style="width:48px;height:48px;border:0;background:none;cursor:pointer;display:grid;place-items:center">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#F5EBD8" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
      </button>
    </div>
    <nav aria-label="التنقل" style="flex:1;display:flex;flex-direction:column;justify-content:center;gap:6px">
      @foreach ($links as $link)
        <a data-menu-item
           href="{{ $link['href'] }}"
           @isset($link['nav'])
             onclick="NB.navTo(event)" data-nav="{{ $link['nav'] }}"
           @else
             onclick="NB.closeMenu(event)"
           @endisset
           style="font-size:clamp(30px,9vw,52px);font-weight:300;color:#FCFAF5;padding:10px 0">{{ $link['label'] }}</a>
      @endforeach
    </nav>
    <div data-menu-item style="display:flex;gap:10px">
      <button type="button" onclick="NB.openCartFromMenu(event)" style="flex:1;border:1px solid rgba(245,235,216,.3);background:transparent;color:#F5EBD8;font-size:15px;padding:16px;border-radius:2px;min-height:52px;cursor:pointer">السلة</button>
      <button type="button" onclick="NB.openSearchFromMenu(event)" style="flex:1;border:0;background:#176B3A;color:#FCFAF5;font-size:15px;padding:16px;border-radius:2px;min-height:52px;cursor:pointer">بحث</button>
    </div>
  </div>
</div>
