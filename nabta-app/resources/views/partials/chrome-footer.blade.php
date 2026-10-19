{{-- Ported from assets/js/nabta-chrome.js footerHTML(). --}}
@php
    $quick = [
        [route('home'),            'الرئيسية'],
        [route('shop'),            'كل المنتجات'],
        [route('categories'),      'الأقسام'],
        [route('home').'#story',   'من نحن'],
        [route('home').'#contact', 'تواصل معنا'],
    ];
    $footerCats = \App\Models\Category::active()->orderBy('sort_order')->take(5)->get();
@endphp

<footer style="background:#0E2A1B;color:#F5EBD8;padding:clamp(60px,8vw,100px) clamp(16px,3vw,44px) 30px;border-top:1px solid rgba(245,235,216,.1)">
  <div style="max-width:1560px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:40px;padding-bottom:50px;border-bottom:1px solid rgba(245,235,216,.1)">
    <div>
      <img src="{{ asset('assets/logo.webp') }}" alt="نبته NABTA" width="130" height="86" style="height:46px;width:auto;object-fit:contain;filter:brightness(0) invert(1);margin-bottom:18px">
      <p style="margin:0;font-size:14px;line-height:1.85;color:rgba(245,235,216,.75);max-width:32ch">منتجات طبيعية مختارة بعناية، من العطارة الأصيلة بروح عصرية تناسب حياتك اليومية.</p>
    </div>
    <div>
      <p style="margin:0 0 16px;font-size:13px;letter-spacing:.2em;color:#B08D57;font-weight:600">الأقسام</p>
      <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;font-size:14px;color:rgba(245,235,216,.8)">
        @foreach ($footerCats as $c)
          <li><a class="nb-footlink" href="{{ route('shop', ['cat' => $c->slug]) }}">{{ $c->name_ar }}</a></li>
        @endforeach
      </ul>
    </div>
    <div>
      <p style="margin:0 0 16px;font-size:13px;letter-spacing:.2em;color:#B08D57;font-weight:600">روابط سريعة</p>
      <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;font-size:14px;color:rgba(245,235,216,.8)">
        @foreach ($quick as [$href, $label])
          <li><a class="nb-footlink" href="{{ $href }}">{{ $label }}</a></li>
        @endforeach
      </ul>
    </div>
    <div>
      <p style="margin:0 0 16px;font-size:13px;letter-spacing:.2em;color:#B08D57;font-weight:600">خدمة العملاء</p>
      <p style="margin:0 0 8px;font-size:14px;color:rgba(245,235,216,.8)">{{ \App\Models\Setting::get('store_address_ar', 'القاهرة، جمهورية مصر العربية') }}</p>
      <p style="margin:0 0 8px;font-size:14px;color:rgba(245,235,216,.8)">{{ \App\Models\Setting::get('store_phone', '+20 100 000 0000') }}</p>
      <p style="margin:0;font-size:14px;color:rgba(245,235,216,.8)">الدفع عند الاستلام في جميع المحافظات</p>
    </div>
  </div>
  <div style="max-width:1560px;margin:24px auto 0;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;font-size:13px;color:rgba(245,235,216,.5)">
    <p style="margin:0">© {{ date('Y') }} NABTA نبته — جميع الحقوق محفوظة</p>
    <div style="display:flex;gap:18px">
      <a class="nb-botlink" href="{{ route('home') }}">الرئيسية</a>
      <a href="{{ route('categories') }}" style="color:#B08D57">الأقسام</a>
    </div>
  </div>
</footer>
