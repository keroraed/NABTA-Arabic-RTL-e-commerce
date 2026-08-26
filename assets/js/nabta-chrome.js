/* ============================================================================
   NABTA — shared header/footer for the new commerce pages.
   ----------------------------------------------------------------------------
   These pages are the DESIGN + UX SPEC for the framework rebuild, so the chrome
   is injected once here rather than copy-pasted into four files.

   MIGRATION: in the framework this becomes a single Layout component and should
   be server-rendered (these pages are client-injected, which is fine for a
   prototype but must NOT ship as-is — a product page needs its nav in the HTML
   for SEO). See HANDOFF.md.
   ========================================================================== */
(function (w, d) {
  'use strict';

  var NAV = [
    { label: 'الرئيسية',    href: 'NABTA.dc.html#home',    key: 'home' },
    { label: 'المنتجات',    href: 'shop.html',             key: 'shop' },
    { label: 'الأقسام',     href: 'categories.html',       key: 'categories' },
    { label: 'من نحن',      href: 'NABTA.dc.html#story',   key: 'story' },
    { label: 'تواصل معنا',  href: 'NABTA.dc.html#contact', key: 'contact' }
  ];

  function headerHTML(active) {
    var links = NAV.map(function (n) {
      var on = n.key === active;
      return '<a href="' + n.href + '" style="font-size:15px;font-weight:' + (on ? '600' : '500') +
        ';color:' + (on ? '#0E2A1B' : '#2C3E30') + ';padding:8px 2px;white-space:nowrap' +
        (on ? ';border-bottom:2.5px solid #176B3A' : ';transition:color .2s') + '"' +
        (on ? '' : ' onmouseover="this.style.color=\'#176B3A\'" onmouseout="this.style.color=\'#2C3E30\'"') +
        '>' + n.label + '</a>';
    }).join('');

    return '' +
    '<header style="position:fixed;inset-inline:0;top:0;z-index:90">' +
      '<div style="position:absolute;inset:0;background:rgba(252,250,245,.94);backdrop-filter:blur(16px);border-bottom:1px solid rgba(14,42,27,.08);box-shadow:0 4px 24px rgba(14,42,27,.04)"></div>' +
      '<div style="position:relative;max-width:1560px;margin:0 auto;padding:0 clamp(16px,3vw,44px);height:76px;display:flex;align-items:center;justify-content:space-between;gap:20px">' +
        '<a href="NABTA.dc.html#home" style="display:flex;align-items:center;gap:12px;flex:0 0 auto">' +
          '<img src="assets/logo.webp" alt="نبته NABTA" width="130" height="86" style="height:clamp(38px,4.2vw,50px);width:auto;object-fit:contain;display:block">' +
        '</a>' +
        '<nav data-nabta-nav aria-label="التنقل الرئيسي" style="display:flex;align-items:center;gap:clamp(14px,2.2vw,36px)">' + links + '</nav>' +
        '<div style="display:flex;align-items:center;gap:8px;flex:0 0 auto">' +
          '<a href="shop.html" aria-label="تصفّح المنتجات" style="width:42px;height:42px;display:grid;place-items:center;border-radius:50%;transition:background .2s" onmouseover="this.style.background=\'rgba(14,42,27,.06)\'" onmouseout="this.style.background=\'none\'">' +
            '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4.3-4.3"/></svg>' +
          '</a>' +
          '<a href="checkout.html" aria-label="سلة التسوق" style="position:relative;width:42px;height:42px;display:grid;place-items:center;border-radius:50%;transition:background .2s" onmouseover="this.style.background=\'rgba(14,42,27,.06)\'" onmouseout="this.style.background=\'none\'">' +
            '<svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>' +
            '<span data-cart-badge style="position:absolute;top:2px;inset-inline-end:2px;min-width:18px;height:18px;padding:0 5px;border-radius:9999px;background:#176B3A;color:#FCFAF5;font-size:11px;font-weight:700;display:grid;place-items:center;display:none">0</span>' +
          '</a>' +
        '</div>' +
      '</div>' +
    '</header>';
  }

  function footerHTML() {
    var quick = [
      ['NABTA.dc.html#home', 'الرئيسية'],
      ['shop.html', 'كل المنتجات'],
      ['categories.html', 'الأقسام'],
      ['NABTA.dc.html#story', 'من نحن'],
      ['NABTA.dc.html#contact', 'تواصل معنا']
    ].map(function (r) {
      return '<li><a href="' + r[0] + '" style="transition:color .2s" onmouseover="this.style.color=\'#B08D57\'" onmouseout="this.style.color=\'rgba(245,235,216,.8)\'">' + r[1] + '</a></li>';
    }).join('');

    var cats = (w.NABTA_CATEGORIES || []).slice(0, 5).map(function (c) {
      return '<li><a href="shop.html?cat=' + c.slug + '" style="transition:color .2s" onmouseover="this.style.color=\'#B08D57\'" onmouseout="this.style.color=\'rgba(245,235,216,.8)\'">' + c.name + '</a></li>';
    }).join('');

    return '' +
    '<footer style="background:#0E2A1B;color:#F5EBD8;padding:clamp(60px,8vw,100px) clamp(16px,3vw,44px) 30px;border-top:1px solid rgba(245,235,216,.1)">' +
      '<div style="max-width:1560px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:40px;padding-bottom:50px;border-bottom:1px solid rgba(245,235,216,.1)">' +
        '<div>' +
          '<img src="assets/logo.webp" alt="نبته NABTA" width="130" height="86" style="height:46px;width:auto;object-fit:contain;filter:brightness(0) invert(1);margin-bottom:18px">' +
          '<p style="margin:0;font-size:14px;line-height:1.85;color:rgba(245,235,216,.75);max-width:32ch">منتجات طبيعية مختارة بعناية، من العطارة الأصيلة بروح عصرية تناسب حياتك اليومية.</p>' +
        '</div>' +
        '<div>' +
          '<p style="margin:0 0 16px;font-size:13px;letter-spacing:.2em;color:#B08D57;font-weight:600">الأقسام</p>' +
          '<ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;font-size:14px;color:rgba(245,235,216,.8)">' + cats + '</ul>' +
        '</div>' +
        '<div>' +
          '<p style="margin:0 0 16px;font-size:13px;letter-spacing:.2em;color:#B08D57;font-weight:600">روابط سريعة</p>' +
          '<ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;font-size:14px;color:rgba(245,235,216,.8)">' + quick + '</ul>' +
        '</div>' +
        '<div>' +
          '<p style="margin:0 0 16px;font-size:13px;letter-spacing:.2em;color:#B08D57;font-weight:600">خدمة العملاء</p>' +
          '<p style="margin:0 0 8px;font-size:14px;color:rgba(245,235,216,.8)">القاهرة، جمهورية مصر العربية</p>' +
          '<p style="margin:0 0 8px;font-size:14px;color:rgba(245,235,216,.8)">+20 100 000 0000</p>' +
          '<p style="margin:0;font-size:14px;color:rgba(245,235,216,.8)">الدفع عند الاستلام في جميع المحافظات</p>' +
        '</div>' +
      '</div>' +
      '<div style="max-width:1560px;margin:24px auto 0;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;font-size:13px;color:rgba(245,235,216,.5)">' +
        '<p style="margin:0">© 2026 NABTA نبته — جميع الحقوق محفوظة</p>' +
        '<div style="display:flex;gap:18px"><a href="NABTA.dc.html#home" style="transition:color .2s" onmouseover="this.style.color=\'#F5EBD8\'" onmouseout="this.style.color=\'rgba(245,235,216,.5)\'">الرئيسية</a><a href="categories.html" style="color:#B08D57">الأقسام</a></div>' +
      '</div>' +
    '</footer>';
  }

  function mount(active) {
    var h = d.querySelector('[data-nabta-header]');
    var f = d.querySelector('[data-nabta-footer]');
    if (h) h.innerHTML = headerHTML(active);
    if (f) f.innerHTML = footerHTML();
    if (w.NabtaShop) w.NabtaShop.bindCartBadge('[data-cart-badge]');
  }

  w.NabtaChrome = { mount: mount };
})(window, document);
