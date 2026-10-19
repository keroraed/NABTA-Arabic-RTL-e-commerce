@extends('layouts.storefront')

@section('title', 'نبته NABTA — منتجات طبيعية من العطارة الأصيلة')

@push('head')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/lenis@1.1.18/dist/lenis.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <style>[x-cloak]{display:none!important}</style>
@endpush

@section('body')

<div data-nabta-root data-motion style="font-family:'IBM Plex Sans Arabic',system-ui,sans-serif;background:#FCFAF5;color:#0E2A1B;position:relative;overflow-x:clip;width:100%">

<div data-pre style="position:fixed;inset:0;z-index:200;background:#0E2A1B;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:22px">
  <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.06) .5px,transparent .5px);background-size:3px 3px;pointer-events:none"></div>
  <img data-pre-logo src="{{ asset('assets/logo.webp') }}" alt="نبته NABTA" width="200" height="133" style="width:clamp(150px,20vw,220px);height:auto;opacity:0;transform:scale(.94);filter:brightness(0) invert(1)">
  <div style="width:min(240px,50vw);height:1px;background:rgba(245,235,216,.2);position:relative;overflow:hidden">
    <div data-pre-line style="position:absolute;inset:0;background:linear-gradient(90deg,transparent,#B08D57,transparent);transform:scaleX(0);transform-origin:center"></div>
  </div>
</div>

<header data-header style="position:fixed;inset-inline:0;top:0;z-index:80">
  <div data-headerbg style="position:absolute;inset:0;background:rgba(252,250,245,0);border-bottom:1px solid rgba(14,42,27,0);transition:background-color .3s ease, border-color .3s ease"></div>
  <div data-hbar style="position:relative;max-width:1560px;margin:0 auto;padding:0 clamp(16px,3vw,44px);height:88px;display:flex;align-items:center;justify-content:space-between;gap:20px;box-sizing:border-box">

    <!-- Right Side (in RTL): Brand Logo -->
    <a href="#home" onclick="NB.navTo(event)" data-nav="home" style="display:flex;align-items:center;gap:12px;flex:0 0 auto">
      <img src="{{ asset('assets/logo.webp') }}" alt="نبته NABTA" width="130" height="86" style="height:clamp(42px, 4.4vw, 54px);width:auto;object-fit:contain;display:block">
      <span style="display:inline-block;width:1px;height:24px;background:rgba(14,42,27,.15);margin-inline-start:4px"></span>
    </a>

    <!-- Center: Main Navigation Links -->
    <div class="nb-desk">
      <nav aria-label="التنقل الرئيسي" style="display:flex;align-items:center;gap:clamp(18px,2.4vw,40px)">
        <a class="nbh-1" href="#home" onclick="NB.navTo(event)" data-nav="home" style="font-size:15px;font-weight:600;color:#0E2A1B;padding:8px 2px;position:relative;border-bottom:2px solid #176B3A">الرئيسية</a>
        <a class="nbh-2" href="#story" onclick="NB.navTo(event)" data-nav="story" style="font-size:15px;font-weight:500;color:#2C3E30;padding:8px 2px">من نحن</a>
        <a class="nbh-2" href="#categories" onclick="NB.navTo(event)" data-nav="categories" style="font-size:15px;font-weight:500;color:#2C3E30;padding:8px 2px">الأقسام</a>
        <a class="nbh-2" href="#products" onclick="NB.navTo(event)" data-nav="products" style="font-size:15px;font-weight:500;color:#2C3E30;padding:8px 2px">المنتجات</a>
        <a class="nbh-2" href="#contact" onclick="NB.navTo(event)" data-nav="contact" style="font-size:15px;font-weight:500;color:#2C3E30;padding:8px 2px">تواصل معنا</a>
      </nav>
    </div>

    <!-- Left Side: Actions (Search, User, Cart) -->
    <div style="display:flex;align-items:center;gap:8px;flex:0 0 auto">
      <button class="nbh-3" type="button" onclick="NB.openSearch(event)" aria-label="بحث" style="width:44px;height:44px;display:grid;place-items:center;background:none;border:0;cursor:pointer;border-radius:50%;transition:background .2s">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4.3-4.3"/></svg>
      </button>
      <div class="nb-desk">
        <button class="nbh-3" type="button" aria-label="حسابي" style="width:44px;height:44px;display:grid;place-items:center;background:none;border:0;cursor:pointer;border-radius:50%;transition:background .2s">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg>
        </button>
      </div>
      <button class="nbh-3" type="button" onclick="NB.openCart(event)" aria-label="سلة المشتريات" style="position:relative;width:44px;height:44px;display:grid;place-items:center;background:none;border:0;cursor:pointer;border-radius:50%;transition:background .2s">
        <svg data-cart-icon width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <span data-cart-badge x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count" style="position:absolute;top:2px;inset-inline-start:2px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#176B3A;color:#FCFAF5;font-size:11px;font-weight:700;display:grid;place-items:center;box-sizing:border-box"></span>
      </button>
      <div class="nb-mob">
        <button type="button" onclick="NB.openMenu(event)" aria-label="القائمة" style="width:44px;height:44px;display:grid;place-items:center;background:none;border:0;cursor:pointer;border-radius:50%">
          <span style="display:block;width:22px">
            <span data-hbg style="display:block;height:1.8px;background:#0E2A1B;margin-bottom:6px;border-radius:1px"></span>
            <span data-hbg style="display:block;height:1.8px;background:#0E2A1B;width:15px;border-radius:1px"></span>
          </span>
        </button>
      </div>
    </div>
  </div>
</header>

<section id="home" data-hero style="position:relative;min-height:100svh;background:#FCFAF5;overflow:hidden;display:flex;align-items:center;box-sizing:border-box">

  <!-- Hero Background Image (responsive: hero-section.webp on desktop, hero-section-mobile.webp on mobile) -->
  <div data-hero-bg style="position:absolute;inset:0;will-change:transform"></div>

  <!-- Soft gradient overlay to guarantee exquisite typography readability -->
  <div style="position:absolute;inset:0;background:linear-gradient(to left, rgba(252,250,245,.4) 0%, rgba(252,250,245,.15) 38%, transparent 70%);pointer-events:none"></div>
  <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(14,42,27,.025) .5px,transparent .5px);background-size:3px 3px;pointer-events:none"></div>

  <!-- 100% Authentic Floating Badge on the right side next to the product packages (Desktop only) -->
  <div data-hero-badge style="position:absolute;top:46%;inset-inline-end:clamp(16px,4vw,64px);width:clamp(74px,7.5vw,94px);height:clamp(74px,7.5vw,94px);border-radius:50%;border:1.5px solid #176B3A;background:rgba(252,250,245,.88);backdrop-filter:blur(8px);box-shadow:0 12px 28px rgba(14,42,27,.12);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;z-index:10;pointer-events:none;animation:nbFloatB 12s ease-in-out infinite">
    <div style="position:absolute;inset:3px;border-radius:50%;border:1px dashed rgba(23,107,58,.4);pointer-events:none"></div>
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="margin-bottom:2px">
      <path d="M12 22C12 22 12.5 13 21 12c.6 7.6-4 9.6-9 10Z" fill="#176B3A"/>
      <path d="M12 22C12 22 10.6 15.4 3 15.4c-.3 6.3 3.7 7.4 9 6.6Z" fill="#176B3A" opacity=".7"/>
    </svg>
    <span style="font-family:'Jost',sans-serif;font-size:clamp(13px,1.1vw,16px);font-weight:700;color:#0E2A1B;line-height:1">100%</span>
    <span style="font-size:clamp(10px,.8vw,12px);font-weight:600;color:#176B3A;line-height:1.1;margin-top:2px">أصيل</span>
  </div>

  <!-- Hero Content (Positioned in open sky area on mobile, left side on desktop) -->
  <div data-hero-content style="position:relative;z-index:10;max-width:1560px;margin:0 auto;width:100%;padding:120px clamp(16px,4vw,60px) 90px;box-sizing:border-box;display:flex">
    <div data-hero-box style="margin-right:auto;max-width:min(540px,94%);text-align:right">
      
      <!-- Main Headline -->
      <h1 style="margin:0;font-weight:700;color:#0E2A1B;line-height:1.14;letter-spacing:-.015em;text-wrap:balance">
        <span data-hero-line-1 style="display:block;font-size:clamp(2.5rem,5.2vw,4.4rem);font-weight:700">من الطبيعة...</span>
        <span data-hero-line-2 style="display:block;font-size:clamp(2.5rem,5.2vw,4.4rem);font-weight:700;color:#0E2A1B;margin-top:2px">إلى بيتك</span>
      </h1>

      <!-- Decorative Botanical Divider Flourish -->
      <div data-hero-divider style="display:flex;align-items:center;justify-content:flex-start;gap:12px;margin:clamp(18px,2.4vw,28px) 0 clamp(16px,2vw,24px);opacity:.9">
        <span style="width:clamp(36px,5vw,60px);height:1px;background:linear-gradient(to left,#B08D57,transparent)"></span>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="12" r="3" fill="#B08D57"/>
          <path d="M12 3c0 4.5-3.5 6-3.5 6s4.5.5 6-3.5c0 0-1 4.5 3.5 6 0 0-4.5.5-6 3.5 0 0 .5-4.5-3.5-6 0 0 4.5-.5 3.5-6Z" fill="#B08D57" opacity=".7"/>
          <circle cx="12" cy="5" r="1" fill="#176B3A"/>
          <circle cx="12" cy="19" r="1" fill="#176B3A"/>
          <circle cx="5" cy="12" r="1" fill="#176B3A"/>
          <circle cx="19" cy="12" r="1" fill="#176B3A"/>
        </svg>
        <span style="width:clamp(36px,5vw,60px);height:1px;background:linear-gradient(to right,#B08D57,transparent)"></span>
      </div>

      <!-- Subtitle Description -->
      <p data-hero-sub style="margin:0 0 clamp(24px,3.2vw,36px);max-width:42ch;font-size:clamp(16px,1.4vw,19px);line-height:1.8;font-weight:400;color:#384638;text-wrap:pretty">
        منتجات طبيعية مختارة بعناية،<br>
        من العطارة الأصيلة بروح عصرية.
      </p>

      <!-- Action Buttons -->
      <div data-hero-cta style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <button class="nbh-4" type="button" data-magnet onmousemove="NB.magMove(event)" onmouseleave="NB.magLeave(event)" onclick="NB.goProducts(event)" style="border:0;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;justify-content:center;gap:10px;background:#0E2A1B;color:#FCFAF5;font-size:15px;font-weight:600;padding:14px 34px;border-radius:9999px;min-height:50px;box-shadow:0 12px 26px rgba(14,42,27,.22);transition:background-color .25s ease,transform .25s ease">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="transform:rotate(180deg)"><path d="m9 18 6-6-6-6"/></svg>
          <span>تسوق الآن</span>
        </button>
        <button class="nbh-5" type="button" data-magnet onmousemove="NB.magMove(event)" onmouseleave="NB.magLeave(event)" onclick="NB.goCategories(event)" style="cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;justify-content:center;background:rgba(252,250,245,.4);backdrop-filter:blur(4px);color:#0E2A1B;font-size:15px;font-weight:500;padding:13px 30px;border:1.5px solid #8E9B89;border-radius:9999px;min-height:50px;transition:all .25s ease">
          اكتشف منتجاتنا
        </button>
      </div>

    </div>
  </div>

  <!-- Bottom Left Slider Indicator Dots -->
  <div data-hero-dots style="position:absolute;bottom:32px;inset-inline-start:clamp(16px,4vw,60px);display:flex;align-items:center;gap:8px;z-index:10">
    <span style="width:22px;height:8px;border-radius:4px;background:#176B3A;display:inline-block"></span>
    <span style="width:8px;height:8px;border-radius:50%;background:#8E9B89;opacity:.65;display:inline-block;cursor:pointer"></span>
    <span style="width:8px;height:8px;border-radius:50%;background:#8E9B89;opacity:.65;display:inline-block;cursor:pointer"></span>
  </div>

</section>

<section id="story" style="position:relative;background:#F7F3E9;padding:clamp(60px,7.5vw,110px) clamp(16px,3.5vw,56px);overflow:hidden">
  
  <!-- Subtle Linen Texture & Botanical Watermark -->
  <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(107,70,48,.09) .5px,transparent .5px);background-size:3px 3px;pointer-events:none"></div>
  <svg viewBox="0 0 240 320" fill="none" style="position:absolute;top:0;inset-inline-end:0;width:clamp(220px,26vw,380px);height:auto;opacity:.08;pointer-events:none;z-index:0" aria-hidden="true">
    <path d="M120 310C120 310 115 180 120 10" stroke="#B08D57" stroke-width="1.5"/>
    <path d="M120 80c0 0 42-30 76-10-10 38-50 44-76 10Z" stroke="#0E2A1B" stroke-width="1.3" fill="rgba(23,107,58,.04)"/>
    <path d="M120 140c0 0-42-30-76-10 10 38 50 44 76 10Z" stroke="#0E2A1B" stroke-width="1.3" fill="rgba(23,107,58,.04)"/>
    <path d="M120 200c0 0 38-26 70-8-8 34-46 38-70 8Z" stroke="#0E2A1B" stroke-width="1.3" fill="rgba(23,107,58,.04)"/>
    <path d="M120 260c0 0-38-26-70-8 8 34 46 38 70 8Z" stroke="#0E2A1B" stroke-width="1.3" fill="rgba(23,107,58,.04)"/>
  </svg>

  <div style="position:relative;max-width:1560px;margin:0 auto;z-index:2">
    <div class="story-grid">

      <!-- Left Column: Architectural Arch Window & Product Still Life -->
      <div data-story-visual data-reveal style="position:relative;width:100%;max-width:560px;margin:0 auto;display:flex;align-items:flex-end;justify-content:center;padding-top:20px">
        <!-- Arched Window Backdrop -->
        <div style="position:absolute;bottom:0;width:85%;height:90%;border-radius:320px 320px 0 0;border:1.5px solid rgba(176,141,87,.38);background:radial-gradient(circle at 50% 32%,rgba(188,198,172,.35),rgba(238,228,214,.8));overflow:hidden;pointer-events:none;z-index:1;box-shadow:inset 0 0 30px rgba(176,141,87,.08)">
          <!-- Nature Green Sunlit Glow inside the Arch -->
          <div style="position:absolute;inset:0;background:radial-gradient(circle at 35% 25%,rgba(130,160,110,.25),transparent 65%)"></div>
          <div style="position:absolute;top:14%;inset-inline-start:12%;width:76%;height:60%;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.4),transparent 70%);filter:blur(18px)"></div>
        </div>

        <!-- Product Image Still Life (assets/bg/second-section.webp) -->
        <img src="{{ asset('assets/bg/second-section.webp') }}" alt="منتجات نبته الطبيعية" loading="lazy" decoding="async" style="position:relative;z-index:2;width:104%;max-width:none;height:auto;object-fit:contain;filter:drop-shadow(0 24px 34px rgba(14,42,27,.2));transform:translateZ(0);will-change:transform"/>
      </div>

      <!-- Right Column: Editorial Headline, Statement & 4 Pillars -->
      <div data-story-content style="display:flex;flex-direction:column;align-items:flex-start;text-align:right">
        
        <!-- Eyebrow: — ◆ نبته ◆ — -->
        <div data-reveal style="display:inline-flex;align-items:center;gap:10px;margin-bottom:clamp(12px,1.8vw,20px)">
          <span style="width:26px;height:1px;background:#B08D57;display:inline-block"></span>
          <span style="color:#B08D57;font-size:9px;line-height:1">◆</span>
          <span style="font-size:clamp(12px,1.1vw,14px);letter-spacing:.25em;color:#6B4630;font-weight:600">نبته</span>
          <span style="color:#B08D57;font-size:9px;line-height:1">◆</span>
          <span style="width:26px;height:1px;background:#B08D57;display:inline-block"></span>
        </div>

        <!-- Main Headline -->
        <h2 style="margin:0;font-size:clamp(2.3rem,5vw,4.4rem);line-height:1.18;font-weight:700;letter-spacing:-.02em">
          <span data-word-line style="display:block;overflow:hidden"><span style="display:block;color:#0E2A1B;font-weight:700">أصالة العطارة...</span></span>
          <span data-word-line style="display:block;overflow:hidden"><span style="display:block;color:#2D6A4F;font-weight:400;margin-top:4px">بروح جديدة</span></span>
        </h2>

        <!-- Description Statement -->
        <div data-reveal style="margin-top:clamp(18px,2.2vw,26px);max-width:48ch">
          <p style="margin:0;font-size:clamp(15px,1.2vw,17.5px);line-height:1.95;font-weight:400;color:#3A483D;text-wrap:pretty">
            نؤمن أن الطبيعة هي مصدر الصحة والجمال،<br>
            لذا نختار لك أفضل الأعشاب والتوابل والمكسرات والعسل الطبيعي<br>
            بجودة عالية وطريقة تحفظ قيمتها وفوائدها.
          </p>
        </div>

        <!-- 4 Feature Badges (in RTL order: إلى بيتك, من العطارة, مختار بعناية, طبيعي) -->
        <div data-reveal style="width:100%;margin-top:clamp(32px,4vw,50px);padding-top:clamp(22px,2.8vw,32px);border-top:1px solid rgba(176,141,87,.22)">
          <div class="story-features">
            
            <!-- Feature 1: إلى بيتك (Rightmost in RTL) -->
            <div class="story-feature-item" style="display:flex;flex-direction:column;align-items:center;text-align:center;cursor:default">
              <div class="story-feature-circle" style="width:58px;height:58px;border-radius:50%;background:#EDE5D6;border:1px solid rgba(176,141,87,.28);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;box-shadow:0 4px 12px rgba(14,42,27,.04)">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                  <path d="m3.3 7 8.7 5 8.7-5"/>
                  <path d="M12 22V12"/>
                </svg>
              </div>
              <h4 style="margin:0 0 4px;font-size:15px;font-weight:700;color:#0E2A1B;white-space:nowrap">إلى بيتك</h4>
              <p style="margin:0;font-size:12px;line-height:1.45;color:#5C6D5F">توصيل سريع وآمن<br>لباب بيتك</p>
            </div>

            <!-- Feature 2: من العطارة -->
            <div class="story-feature-item" style="display:flex;flex-direction:column;align-items:center;text-align:center;cursor:default">
              <div class="story-feature-circle" style="width:58px;height:58px;border-radius:50%;background:#EDE5D6;border:1px solid rgba(176,141,87,.28);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;box-shadow:0 4px 12px rgba(14,42,27,.04)">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="m19 4-4.8 6.5"/>
                  <path d="M3.5 10.5h17c0 4.8-3.8 8.8-8.5 8.8s-8.5-4-8.5-8.8Z"/>
                  <path d="M8 21.5h8"/>
                  <path d="M12 19.3v2.2"/>
                </svg>
              </div>
              <h4 style="margin:0 0 4px;font-size:15px;font-weight:700;color:#0E2A1B;white-space:nowrap">من العطارة</h4>
              <p style="margin:0;font-size:12px;line-height:1.45;color:#5C6D5F">خبرة وأصالة<br>تصل إليك</p>
            </div>

            <!-- Feature 3: مختار بعناية -->
            <div class="story-feature-item" style="display:flex;flex-direction:column;align-items:center;text-align:center;cursor:default">
              <div class="story-feature-circle" style="width:58px;height:58px;border-radius:50%;background:#EDE5D6;border:1px solid rgba(176,141,87,.28);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;box-shadow:0 4px 12px rgba(14,42,27,.04)">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M12 10V3"/>
                  <path d="m9 6 3-3 3 3"/>
                  <path d="M7 10.5a3.5 3.5 0 0 1 5-3.1 3.5 3.5 0 0 1 5 3.1"/>
                  <path d="M19 14.5c1.4.4 2 1.5 2 2.5 0 2.2-2 4-5 4H8c-3 0-5-1.8-5-4 0-1 .6-2.1 2-2.5"/>
                  <path d="M6 14.5h12"/>
                </svg>
              </div>
              <h4 style="margin:0 0 4px;font-size:15px;font-weight:700;color:#0E2A1B;white-space:nowrap">مختار بعناية</h4>
              <p style="margin:0;font-size:12px;line-height:1.45;color:#5C6D5F">منتجات مختارة<br>من أفضل المصادر</p>
            </div>

            <!-- Feature 4: طبيعي (Leftmost in RTL) -->
            <div class="story-feature-item" style="display:flex;flex-direction:column;align-items:center;text-align:center;cursor:default">
              <div class="story-feature-circle" style="width:58px;height:58px;border-radius:50%;background:#EDE5D6;border:1px solid rgba(176,141,87,.28);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;box-shadow:0 4px 12px rgba(14,42,27,.04)">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                  <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                </svg>
              </div>
              <h4 style="margin:0 0 4px;font-size:15px;font-weight:700;color:#0E2A1B;white-space:nowrap">طبيعي</h4>
              <p style="margin:0;font-size:12px;line-height:1.45;color:#5C6D5F">100% طبيعي<br>بدون أي إضافات</p>
            </div>

          </div>
        </div>

      </div>

    </div>
  </div>
</section>


<section id="categories" style="position:relative;background:#F5EBD8;padding:clamp(72px,10vw,150px) clamp(16px,3vw,44px)">
  <div style="max-width:1560px;margin:0 auto">
    <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:clamp(36px,5vw,64px)">
      <div>
        <p data-reveal style="margin:0 0 14px;font-size:clamp(11px,1.1vw,13px);letter-spacing:.34em;color:#6B4630;font-weight:500">الأقسام</p>
        <h2 style="margin:0;font-size:clamp(1.9rem,4.4vw,3.6rem);line-height:1.2;font-weight:600;letter-spacing:-.02em">
          <span data-word-line style="display:block;overflow:hidden"><span style="display:block">تنقّل بين رفوف العطارة</span></span>
        </h2>
      </div>
      <p data-reveal style="margin:0;max-width:36ch;font-size:15px;line-height:1.9;font-weight:300;color:#4a5a4e;text-wrap:pretty">أقسام متنوعة تجمع ما تحتاجه من الأعشاب والتوابل والمكسرات والعناية الطبيعية.</p>
    </div>

    {{-- Five shelf tiles. Each is a different physical object, so its height and
         both artwork positions are art-directed in config/nabta.php rather than
         derived from the product row. --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:clamp(14px,1.6vw,24px);align-items:end">

      @foreach ($tiles as $tile)
        @continue(! $tile['product'])
        @php($p = $tile['product'])
        <a href="{{ route('shop', ['cat' => $tile['category']]) }}" data-tile="{{ $tile['legacy_id'] }}" onmouseenter="NB.tileEnter(event)" onmouseleave="NB.tileLeave(event)" onmousemove="NB.tileMove(event)" style="position:relative;display:block;height:{{ $tile['height'] }};border-radius:200px 200px 3px 3px;background:transparent;overflow:visible;text-decoration:none">
          <div data-tile-shelf style="position:absolute;inset:0;border-radius:200px 200px 3px 3px;background:#FCFAF5;border:1px solid rgba(14,42,27,.1);overflow:hidden;pointer-events:none">
            <div data-tile-bg style="position:absolute;inset:0;background:radial-gradient(90% 70% at 50% 100%,{{ $tile['glow'] }},transparent);opacity:0"></div>
          </div>
          <div data-tile-stage style="position:absolute;inset:0;pointer-events:none;z-index:3;overflow:visible">
            <img data-tile-normal src="{{ $tile['normal_src'] }}" alt="{{ $tile['label'] }}" loading="lazy" decoding="async" style="position:absolute;{{ $tile['normal'] }};inset-inline:0;margin-inline:auto;height:auto;object-fit:contain;filter:drop-shadow(0 16px 22px rgba(14,42,27,.18));will-change:transform,opacity"/>
            <div data-tile-float style="position:absolute;inset:0;pointer-events:none;will-change:transform">
              @if ($tile['opened_src'])
                <img data-tile-opened src="{{ $tile['opened_src'] }}" alt="{{ $tile['label'] }} مفتوحة" loading="lazy" decoding="async" style="position:absolute;{{ $tile['opened'] }};inset-inline:0;margin-inline:auto;height:auto;object-fit:contain;filter:drop-shadow(0 26px 36px rgba(14,42,27,.26));opacity:0;pointer-events:none;will-change:transform,opacity"/>
              @endif
            </div>
          </div>
          <div style="position:absolute;bottom:0;inset-inline:0;z-index:5;padding:24px 20px 20px;display:flex;align-items:flex-end;justify-content:space-between;gap:8px;background:linear-gradient(0deg,rgba(252,250,245,.97) 30%,rgba(252,250,245,0));border-radius:0 0 3px 3px">
            <span data-tile-title style="font-size:clamp(16px,1.5vw,20px);font-weight:600;display:block;color:#0E2A1B">{{ $tile['label'] }}</span>
            <svg data-tile-arrow width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#176B3A" stroke-width="1.75" stroke-linecap="round" style="opacity:0" aria-hidden="true"><path d="M20 12H4"/><path d="m10 6-6 6 6 6"/></svg>
          </div>
        </a>
      @endforeach

    </div>

    <div style="display:flex;justify-content:center;margin-top:clamp(36px,5vw,56px)">
      <a class="nbh-4" href="{{ route('categories') }}" data-magnet onmousemove="NB.magMove(event)" onmouseleave="NB.magLeave(event)" style="cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:12px;background:#0E2A1B;color:#FCFAF5;font-size:15px;font-weight:600;padding:15px 42px;border-radius:9999px;border:0;box-shadow:0 10px 24px rgba(14,42,27,.18);transition:all .25s ease;text-decoration:none">
        <span>عرض كل الأقسام ({{ $categoryCount }})</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="transform:rotate(180deg)"><path d="m9 18 6-6-6-6"/></svg>
      </a>
    </div>

  </div>
</section>

<section id="products" style="position:relative;background:#FCFAF5;padding:clamp(72px,10vw,150px) 0">
  <div style="max-width:1560px;margin:0 auto;padding:0 clamp(16px,3vw,44px)">
    <div data-reveal style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:clamp(30px,4vw,58px)">
      <div>
        <p style="margin:0 0 14px;font-size:clamp(11px,1.1vw,13px);letter-spacing:.34em;color:#6B4630;font-weight:500">مختارات</p>
        <h2 style="margin:0;font-size:clamp(1.9rem,4.4vw,3.6rem);line-height:1.2;font-weight:600;letter-spacing:-.02em">اختيارات نبته</h2>
        <p style="margin:16px 0 0;max-width:40ch;font-size:15px;line-height:1.9;font-weight:300;color:#4a5a4e;text-wrap:pretty">منتجات مختارة بعناية لعشاق الطبيعي والأصيل.</p>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <button class="nbh-6" type="button" data-sw-prev aria-label="السابق" style="width:52px;height:52px;border-radius:50%;border:1px solid rgba(14,42,27,.18);background:transparent;cursor:pointer;display:grid;place-items:center">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M4 12h16"/><path d="m14 6 6 6-6 6"/></svg>
        </button>
        <button class="nbh-6" type="button" data-sw-next aria-label="التالي" style="width:52px;height:52px;border-radius:50%;border:1px solid rgba(14,42,27,.18);background:transparent;cursor:pointer;display:grid;place-items:center">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M20 12H4"/><path d="m10 6-6 6 6 6"/></svg>
        </button>
      </div>
    </div>
  </div>

  <div data-swiper class="swiper" dir="rtl" style="padding:0 clamp(16px,3vw,44px) 10px;box-sizing:border-box;max-width:1560px;margin:0 auto">
    <div class="swiper-wrapper">

      @foreach ($featured as $p)
        <div class="swiper-slide">
          <article data-card data-id="{{ $p->id }}" onmouseenter="NB.cardEnter(event)" onmouseleave="NB.cardLeave(event)" style="position:relative;background:#F5EBD8;border:1px solid rgba(14,42,27,.08);border-radius:3px;padding-top:26px">
            <div data-cardbg style="position:absolute;inset:0;background:#EFE2C7;opacity:0;border-radius:3px"></div>
            <button type="button" data-wish onclick="NB.onWish(event)" data-id="{{ $p->id }}" aria-label="أضف {{ $p->name_ar }} لقائمة الرغبات" style="position:absolute;top:12px;inset-inline-start:12px;z-index:4;width:44px;height:44px;border:0;background:none;cursor:pointer;display:grid;place-items:center">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6B4630" stroke-width="1.4" aria-hidden="true"><path d="M12 20s-7-4.6-7-9.4A4 4 0 0 1 12 7a4 4 0 0 1 7 3.6C19 15.4 12 20 12 20Z"/></svg>
            </button>
            <div style="position:relative;height:clamp(200px,20vw,250px);z-index:2">
              <img data-cardimg src="{{ $p->primary_image_url }}" alt="{{ $p->name_ar }}" loading="lazy" decoding="async" style="{{ $cardArt($p) }}"/>
              <svg data-cardleaf width="46" height="46" viewBox="0 0 48 48" fill="none" style="position:absolute;top:2px;inset-inline-end:6px;opacity:0" aria-hidden="true"><path d="M6 42C6 42 8 18 42 6c2 26-18 38-36 36Z" stroke="#B08D57" stroke-width="1"/></svg>
            </div>
            <div style="position:relative;z-index:3;padding:20px 20px 22px">
              <p style="margin:0 0 7px;font-size:11px;letter-spacing:.2em;color:#6B4630">{{ $p->category?->name_ar }}</p>
              <a class="nbh-2" href="{{ route('products.show', $p) }}" style="display:block;font-size:clamp(17px,1.6vw,21px);font-weight:500;color:#0E2A1B;margin-bottom:14px;text-decoration:none">{{ $p->name_ar }}</a>
              <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                <span style="font-size:16px;font-weight:600;color:#176B3A">{{ number_format($p->price, 0) }} <span style="font-size:12px;font-weight:400;color:#6B4630">ج.م</span></span>
                <button type="button" onclick="NB.onQuick(event)" data-id="{{ $p->id }}" style="background:none;border:0;cursor:pointer;font-size:13px;color:#6B4630;padding:6px 0;min-height:44px;text-decoration:underline;text-underline-offset:4px">نظرة سريعة</button>
              </div>
            </div>
            <button class="nbh-7" type="button" data-addbtn onclick="NB.onAdd(event)" data-id="{{ $p->id }}" @disabled($p->isOutOfStock()) style="position:absolute;z-index:5;inset-inline:14px;bottom:14px;opacity:0;border:0;cursor:pointer;background:#0E2A1B;color:#F5EBD8;font-size:14px;font-weight:500;padding:15px;border-radius:2px;min-height:48px">{{ $p->isOutOfStock() ? 'نفدت الكمية' : 'أضف للسلة' }}</button>
          </article>
        </div>
      @endforeach

    </div>
  </div>
</section>

<section data-film-sec style="position:relative;background:#FCFAF5;padding:clamp(40px,6vw,90px) 0">
  <div data-film style="position:relative;height:min(88svh,780px);overflow:hidden;background:#0E2A1B">
    <video data-film-video muted loop playsinline preload="auto" aria-hidden="true" style="position:absolute;inset:0;width:100%;height:110%;object-fit:cover;opacity:.7;background:#0E2A1B">
      <source src="{{ asset('assets/videos/story.mp4') }}" type="video/mp4">
    </video>
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(14,42,27,.55),rgba(14,42,27,.82))"></div>
    <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.05) .5px,transparent .5px);background-size:3px 3px"></div>
    <div style="position:relative;z-index:2;height:100%;display:flex;flex-direction:column;justify-content:flex-end;padding:clamp(28px,5vw,80px) clamp(20px,4vw,72px);box-sizing:border-box">
      <div data-film-text style="max-width:640px">
        <p style="margin:0 0 16px;font-size:clamp(11px,1.1vw,13px);letter-spacing:.34em;color:#B08D57;font-weight:500">نبته</p>
        <h2 style="margin:0;font-size:clamp(2.2rem,5.6vw,4.8rem);line-height:1.1;font-weight:600;color:#FCFAF5;letter-spacing:-.02em">من قلب الطبيعة</h2>
        <p style="margin:20px 0 0;font-size:clamp(15px,1.4vw,18px);line-height:1.9;font-weight:300;color:rgba(252,250,245,.8);text-wrap:pretty">من مصادرها، إلى الفرز والتعبئة، إلى بيتك. كل خطوة مرئية وواضحة.</p>
      </div>
    </div>
</section>

<section id="best" data-pin style="position:relative;background:#0E2A1B;color:#F5EBD8;min-height:100svh;display:flex;flex-direction:column;justify-content:center;overflow:hidden;padding:clamp(60px,8vw,0px) 0">
  <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.05) .5px,transparent .5px);background-size:3px 3px;pointer-events:none"></div>
  <svg style="position:absolute;top:-40px;inset-inline-end:-40px;width:clamp(180px,24vw,380px);opacity:.14" viewBox="0 0 200 200" fill="none" aria-hidden="true"><circle cx="100" cy="100" r="99" stroke="#B08D57"/><circle cx="100" cy="100" r="70" stroke="#B08D57" stroke-dasharray="2 8"/><circle cx="100" cy="100" r="42" stroke="#B08D57"/></svg>
  <div style="position:relative;max-width:1560px;margin:0 auto;width:100%;padding:0 clamp(16px,3vw,44px);box-sizing:border-box">
    <p style="margin:0 0 14px;font-size:clamp(11px,1.1vw,13px);letter-spacing:.34em;color:#B08D57;font-weight:500">الأكثر طلباً</p>
    <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:20px">
      <h2 style="margin:0;font-size:clamp(1.9rem,4.6vw,3.8rem);line-height:1.16;font-weight:600;letter-spacing:-.02em;color:#FCFAF5">ما يختاره عملاؤنا أولاً</h2>
      <p style="margin:0;font-size:13px;color:rgba(245,235,216,.6)">اسحب أو استمر في التمرير ←</p>
    </div>
  </div>
  <div data-track-wrap style="position:relative;margin-top:clamp(30px,4vw,60px);overflow:hidden" data-hide-scroll>
    <div data-track style="display:flex;gap:clamp(20px,3vw,52px);padding:0 clamp(16px,3vw,44px);width:max-content;align-items:flex-end">

      @foreach ($bestSellers as $p)
        <article data-bs style="width:clamp(230px,25vw,360px);flex:0 0 auto">
          <div style="position:relative;height:clamp(240px,26vw,360px);display:flex;align-items:flex-end;justify-content:center">
            <img src="{{ $p->primary_image_url }}" alt="{{ $p->name_ar }}" loading="lazy" decoding="async" style="width:{{ $bestArt($p) }};height:auto;filter:drop-shadow(0 30px 40px rgba(0,0,0,.5))"/>
          </div>
          <div style="border-top:1px solid rgba(245,235,216,.18);padding-top:18px;margin-top:22px;display:flex;align-items:flex-start;justify-content:space-between;gap:14px">
            <div>
              <p style="margin:0 0 6px;font-size:11px;letter-spacing:.2em;color:#B08D57">{{ $p->category?->name_ar }}</p>
              <h3 style="margin:0;font-size:clamp(18px,1.7vw,23px);font-weight:500;color:#FCFAF5"><a href="{{ route('products.show', $p) }}" style="color:inherit">{{ $p->name_ar }}</a></h3>
              <p style="margin:8px 0 0;font-size:14px;color:rgba(245,235,216,.72)">{{ number_format($p->price, 0) }} ج.م</p>
            </div>
            <button class="nbh-8" type="button" onclick="NB.onAdd(event)" data-id="{{ $p->id }}" @disabled($p->isOutOfStock()) aria-label="أضف {{ $p->name_ar }} للسلة" style="flex:0 0 auto;width:48px;height:48px;border-radius:50%;border:1px solid rgba(245,235,216,.32);background:transparent;cursor:pointer;display:grid;place-items:center">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#F5EBD8" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            </button>
          </div>
        </article>
      @endforeach

    </div>
  </div>
</section>

<section style="background:#F5EBD8;padding:clamp(60px,8vw,120px) clamp(16px,3vw,44px)">
  <div style="max-width:1560px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(178px,1fr));gap:clamp(26px,3vw,48px)">
    <div data-reveal>
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#176B3A" stroke-width="1.3" aria-hidden="true"><path d="M12 21V9"/><path d="M12 9c0 0 1-7 9-7 .6 6.6-3.6 9.4-9 7Z"/></svg>
      <h3 style="margin:16px 0 8px;font-size:19px;font-weight:600">طبيعي</h3>
      <p style="margin:0;font-size:14px;line-height:1.85;color:#4a5a4e">منتجات مختارة بعناية</p>
    </div>
    <div data-reveal>
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#176B3A" stroke-width="1.3" aria-hidden="true"><path d="M4 10h16a8 8 0 0 1-8 8 8 8 0 0 1-8-8Z"/><path d="M12 18v3"/><path d="M9 6c0-2 1.3-3 3-3s3 1 3 3"/></svg>
      <h3 style="margin:16px 0 8px;font-size:19px;font-weight:600">أصيل</h3>
      <p style="margin:0;font-size:14px;line-height:1.85;color:#4a5a4e">خبرة العطارة بروح عصرية</p>
    </div>
    <div data-reveal>
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#176B3A" stroke-width="1.3" aria-hidden="true"><path d="M12 3l7 3v6c0 5-3.2 8-7 9-3.8-1-7-4-7-9V6Z"/><path d="m9 12 2.2 2.2L15.5 10"/></svg>
      <h3 style="margin:16px 0 8px;font-size:19px;font-weight:600">موثوق</h3>
      <p style="margin:0;font-size:14px;line-height:1.85;color:#4a5a4e">جودة نهتم بها</p>
    </div>
    <div data-reveal>
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#176B3A" stroke-width="1.3" aria-hidden="true"><path d="M6 21V11l4-8 1 1v6h6a2 2 0 0 1 2 2l-1.4 8a2 2 0 0 1-2 2Z"/></svg>
      <h3 style="margin:16px 0 8px;font-size:19px;font-weight:600">مختار بعناية</h3>
      <p style="margin:0;font-size:14px;line-height:1.85;color:#4a5a4e">نهتم بكل منتج يصل إليك</p>
    </div>
  </div>
</section>

<footer id="contact" style="position:relative;background:#0E2A1B;color:#F5EBD8;padding:clamp(60px,8vw,120px) clamp(16px,3vw,44px) 40px;overflow:hidden">
  <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.045) .5px,transparent .5px);background-size:3px 3px;pointer-events:none"></div>
  <div style="position:relative;max-width:1560px;margin:0 auto">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:clamp(32px,4vw,64px);padding-bottom:clamp(40px,5vw,72px);border-bottom:1px solid rgba(245,235,216,.14)">
      <div>
        <img src="{{ asset('assets/logo.webp') }}" alt="نبته NABTA" width="150" height="100" loading="lazy" style="width:132px;height:auto;filter:brightness(0) invert(1);opacity:.9"/>
        <p style="margin:20px 0 0;max-width:28ch;font-size:14px;line-height:1.9;color:rgba(245,235,216,.66);text-wrap:pretty">عطارة طبيعية بروح عصرية. منتجات مختارة بعناية، من الطبيعة إلى بيتك.</p>
      </div>
      <nav aria-label="روابط الموقع">
        <h4 style="margin:0 0 18px;font-size:12px;letter-spacing:.24em;color:#B08D57;font-weight:500">الموقع</h4>
        <div style="display:flex;flex-direction:column;gap:12px">
          <a class="nbh-9" href="#home" onclick="NB.navTo(event)" data-nav="home" style="font-size:15px;color:rgba(245,235,216,.82)">الرئيسية</a>
          <a class="nbh-9" href="#story" onclick="NB.navTo(event)" data-nav="story" style="font-size:15px;color:rgba(245,235,216,.82)">من نحن</a>
          <a class="nbh-9" href="#categories" onclick="NB.navTo(event)" data-nav="categories" style="font-size:15px;color:rgba(245,235,216,.82)">الأقسام</a>
          <a class="nbh-9" href="#products" onclick="NB.navTo(event)" data-nav="products" style="font-size:15px;color:rgba(245,235,216,.82)">المنتجات</a>
          <a class="nbh-9" href="#contact" onclick="NB.navTo(event)" data-nav="contact" style="font-size:15px;color:rgba(245,235,216,.82)">تواصل معنا</a>
        </div>
      </nav>
      <nav aria-label="خدمة العملاء">
        <h4 style="margin:0 0 18px;font-size:12px;letter-spacing:.24em;color:#B08D57;font-weight:500">خدمة العملاء</h4>
        <div style="display:flex;flex-direction:column;gap:12px">
          <a class="nbh-9" href="#contact" onclick="NB.navTo(event)" data-nav="contact" style="font-size:15px;color:rgba(245,235,216,.82)">سياسة الاستبدال</a>
          <a class="nbh-9" href="#contact" onclick="NB.navTo(event)" data-nav="contact" style="font-size:15px;color:rgba(245,235,216,.82)">سياسة الخصوصية</a>
          <a class="nbh-9" href="#contact" onclick="NB.navTo(event)" data-nav="contact" style="font-size:15px;color:rgba(245,235,216,.82)">الشروط والأحكام</a>
        </div>
      </nav>
      <div>
        <h4 style="margin:0 0 14px;font-size:12px;letter-spacing:.24em;color:#B08D57;font-weight:500">النشرة</h4>
        <p style="margin:0 0 18px;font-size:19px;font-weight:500;color:#FCFAF5">خليك قريب من نبته</p>
        <form onsubmit="NB.onSubscribe(event)" style="display:flex;gap:8px;flex-wrap:wrap">
          <label for="nb-mail" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">بريدك الإلكتروني</label>
          <input id="nb-mail" type="email" required placeholder="بريدك الإلكتروني" style="flex:1 1 160px;min-width:0;background:transparent;border:1px solid rgba(245,235,216,.28);color:#F5EBD8;font-size:14px;padding:14px 16px;border-radius:2px;min-height:48px;box-sizing:border-box"/>
          <button class="nbh-10" type="submit" style="border:0;cursor:pointer;background:#F5EBD8;color:#0E2A1B;font-size:14px;font-weight:500;padding:14px 24px;border-radius:2px;min-height:48px">اشترك</button>
        </form>
        <p data-sub-msg style="margin:12px 0 0;font-size:13px;color:#B08D57;opacity:0">تم الاشتراك — شكراً لك.</p>
        <div style="display:flex;gap:8px;margin-top:24px">
          <a class="nbh-11" href="#contact" onclick="NB.navTo(event)" data-nav="contact" aria-label="إنستجرام" style="width:44px;height:44px;border-radius:50%;border:1px solid rgba(245,235,216,.24);display:grid;place-items:center"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#F5EBD8" stroke-width="1.4" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="#F5EBD8"/></svg></a>
          <a class="nbh-11" href="#contact" onclick="NB.navTo(event)" data-nav="contact" aria-label="فيسبوك" style="width:44px;height:44px;border-radius:50%;border:1px solid rgba(245,235,216,.24);display:grid;place-items:center"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#F5EBD8" stroke-width="1.4" aria-hidden="true"><path d="M14 9h3V5.5h-3c-2 0-3.4 1.4-3.4 3.4V11H8v3.4h2.6V22H14v-7.6h2.6L17 11h-3V9.4c0-.3.2-.4.6-.4Z"/></svg></a>
          <a class="nbh-11" href="#contact" onclick="NB.navTo(event)" data-nav="contact" aria-label="واتساب" style="width:44px;height:44px;border-radius:50%;border:1px solid rgba(245,235,216,.24);display:grid;place-items:center"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#F5EBD8" stroke-width="1.4" aria-hidden="true"><path d="M21 12a9 9 0 0 1-13.3 7.9L3 21l1.2-4.6A9 9 0 1 1 21 12Z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5"/></svg></a>
        </div>
      </div>
    </div>
    <p style="margin:28px 0 0;font-size:13px;color:rgba(245,235,216,.5)">© 2026 NABTA — جميع الحقوق محفوظة</p>
  </div>
</footer>

@include('partials.overlays')

@include('partials.mobile-menu', ['links' => [
    ['href' => '#home',       'label' => 'الرئيسية',   'nav' => 'home'],
    ['href' => '#story',      'label' => 'من نحن',     'nav' => 'story'],
    ['href' => '#categories', 'label' => 'الأقسام',    'nav' => 'categories'],
    ['href' => '#products',   'label' => 'المنتجات',   'nav' => 'products'],
    ['href' => '#contact',    'label' => 'تواصل معنا', 'nav' => 'contact'],
]])

</div>{{-- /data-nabta-root --}}
@endsection
