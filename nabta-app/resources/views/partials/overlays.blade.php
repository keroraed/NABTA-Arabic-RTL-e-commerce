{{--
  Cart drawer, search overlay and quick-view modal, lifted out of the home page
  so every storefront page shares one set. All three are driven by Alpine stores
  registered in public/js/nabta.js and opened by NB.openCart / openSearch /
  openQuick, so they work identically on any page that includes this partial.
--}}
<div data-drawer role="dialog" aria-modal="true" aria-label="سلة المشتريات" aria-hidden="true" style="position:fixed;inset:0;z-index:120;pointer-events:none;visibility:hidden;opacity:0">
  <div data-drawer-scrim onclick="NB.closeCart(event)" style="position:absolute;inset:0;background:rgba(14,42,27,.5);opacity:0"></div>
  <aside data-drawer-panel style="position:absolute;inset-block:0;inset-inline-start:0;width:min(440px,100%);background:#FCFAF5;display:flex;flex-direction:column;box-shadow:14px 0 60px rgba(14,42,27,.28)">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:24px 24px 18px;border-bottom:1px solid rgba(14,42,27,.1)">
      <h2 style="margin:0;font-size:20px;font-weight:600">سلة المشتريات</h2>
      <button class="nbh-12" type="button" onclick="NB.closeCart(event)" aria-label="إغلاق السلة" style="width:44px;height:44px;border:0;background:none;cursor:pointer;display:grid;place-items:center;border-radius:50%">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
      </button>
    </div>
    <div style="flex:1;overflow-y:auto;padding:8px 24px">
      <template x-if="$store.cart.isEmpty">
        <div>
        <div style="padding:60px 0;text-align:center">
          <p style="margin:0;font-size:15px;color:#6B4630">سلتك فارغة حالياً.</p>
          <button type="button" onclick="NB.closeCartShop(event)" style="margin-top:18px;border:0;cursor:pointer;background:#176B3A;color:#FCFAF5;font-size:14px;padding:14px 26px;border-radius:2px;min-height:48px">تسوق الآن</button>
        </div>
      </div>
      </template>
      <template x-for="it in $store.cart.lines" :key="it.id">
        <div style="display:flex;gap:14px;padding:18px 0;border-bottom:1px solid rgba(14,42,27,.08)">
          <div style="flex:0 0 76px;height:76px;background:#F5EBD8;border-radius:2px;display:grid;place-items:center;overflow:hidden">
            <img :src="it.image" :alt="it.name" loading="lazy" style="width:88%;height:88%;object-fit:contain"/>
          </div>
          <div style="flex:1;min-width:0">
            <p style="margin:0 0 4px;font-size:15px;font-weight:500" x-text="it.name"></p>
            <p style="margin:0 0 10px;font-size:12px;color:#6B4630" x-text="it.cat"></p>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
              <div style="display:flex;align-items:center;border:1px solid rgba(14,42,27,.16);border-radius:2px">
                <button type="button" @click="$store.cart.dec(it)" aria-label="تقليل الكمية" style="width:44px;height:44px;border:0;background:none;cursor:pointer;font-size:16px;color:#0E2A1B;touch-action:manipulation">−</button>
                <span style="min-width:26px;text-align:center;font-size:14px" x-text="it.qty"></span>
                <button type="button" @click="$store.cart.inc(it)" aria-label="زيادة الكمية" style="width:44px;height:44px;border:0;background:none;cursor:pointer;font-size:16px;color:#0E2A1B;touch-action:manipulation">+</button>
              </div>
              <span style="font-size:15px;font-weight:600;color:#176B3A" x-text="$store.cart.lineLabel(it)"></span>
            </div>
          </div>
          <button type="button" @click="$store.cart.remove(it)" aria-label="إزالة المنتج" style="flex:0 0 auto;width:44px;height:44px;margin-top:-8px;margin-inline-end:-10px;border:0;background:none;cursor:pointer;display:grid;place-items:center;align-self:flex-start">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6B4630" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
          </button>
        </div>
      </template>
    </div>
    <div style="padding:20px 24px 26px;border-top:1px solid rgba(14,42,27,.1);background:#F5EBD8">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:6px">
        <span style="font-size:14px;color:#4a5a4e">الإجمالي الفرعي</span>
        <span style="font-size:22px;font-weight:600;color:#0E2A1B" x-text="$store.cart.subtotalLabel"></span>
      </div>
      <p style="margin:0 0 16px;font-size:11px;color:#6B4630">الشحن يُحسب حسب المحافظة في صفحة الطلب</p>
      <a class="nbh-7" href="{{ route('checkout') }}" style="display:block;box-sizing:border-box;width:100%;text-align:center;text-decoration:none;background:#0E2A1B;color:#F5EBD8;font-size:15px;font-weight:500;padding:17px;border-radius:2px;min-height:54px">إتمام الشراء</a>
      <p style="margin:12px 0 0;font-size:12px;color:#6B4630;text-align:center">الدفع عند الاستلام · بدون إنشاء حساب</p>
    </div>
  </aside>
</div>

<div data-search role="dialog" aria-modal="true" aria-label="البحث" aria-hidden="true" style="position:fixed;inset:0;z-index:130;pointer-events:none;visibility:hidden;opacity:0">
  <div data-search-scrim onclick="NB.closeSearch(event)" style="position:absolute;inset:0;background:rgba(14,42,27,.55);opacity:0"></div>
  <div data-search-panel style="position:absolute;inset-inline:0;top:0;background:#FCFAF5;box-shadow:0 30px 70px rgba(14,42,27,.24)">
    <div style="max-width:1000px;margin:0 auto;padding:clamp(26px,4vw,50px) clamp(16px,3vw,32px)">
      <div style="display:flex;align-items:center;gap:14px;border-bottom:1px solid rgba(14,42,27,.18);padding-bottom:14px">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#176B3A" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4.3-4.3"/></svg>
        <label for="nb-search" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">ابحث في المنتجات</label>
        <input id="nb-search" data-search-input type="search" x-model="$store.search.q" placeholder="ابحث عن منتج أو قسم..." style="flex:1;min-width:0;background:none;border:0;outline:none;font-size:clamp(18px,2.4vw,28px);font-weight:300;color:#0E2A1B;padding:6px 0"/>
        <button class="nbh-12" type="button" onclick="NB.closeSearch(event)" aria-label="إغلاق البحث" style="width:44px;height:44px;border:0;background:none;cursor:pointer;display:grid;place-items:center;border-radius:50%">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
        </button>
      </div>
      <div style="max-height:min(52svh,440px);overflow-y:auto;margin-top:8px">
        <template x-for="r in $store.search.results" :key="r.id">
          <div style="display:flex;align-items:center;gap:16px;padding:14px 0;border-bottom:1px solid rgba(14,42,27,.07)">
            <div style="flex:0 0 62px;height:62px;background:#F5EBD8;border-radius:2px;display:grid;place-items:center;overflow:hidden">
              <img :src="r.img" :alt="r.name" loading="lazy" style="width:86%;height:86%;object-fit:contain"/>
            </div>
            <div style="flex:1;min-width:0">
              <p style="margin:0 0 4px;font-size:16px;font-weight:500" x-text="r.name"></p>
              <p style="margin:0;font-size:12px;color:#6B4630" x-text="r.cat"></p>
            </div>
            <span style="font-size:15px;font-weight:600;color:#176B3A" x-text="$store.search.priceLabel(r)"></span>
            <button class="nbh-13" type="button" @click="$store.search.add(r)" style="border:1px solid rgba(14,42,27,.18);background:transparent;cursor:pointer;font-size:13px;padding:12px 18px;border-radius:2px;min-height:44px">أضف</button>
          </div>
        </template>
        <template x-if="$store.search.noResults">
          <div>
          <p style="margin:26px 0;font-size:15px;color:#6B4630">لا توجد نتائج مطابقة.</p>
        </div>
        </template>
      </div>
    </div>
  </div>
</div>

<div data-quick role="dialog" aria-modal="true" aria-label="تفاصيل المنتج" aria-hidden="true" style="position:fixed;inset:0;z-index:140;pointer-events:none;visibility:hidden;opacity:0;display:grid;place-items:center;padding:clamp(12px,3vw,40px);box-sizing:border-box">
  <div data-quick-scrim onclick="NB.closeQuick(event)" style="position:absolute;inset:0;background:rgba(14,42,27,.6);opacity:0"></div>
  <div data-quick-panel style="position:relative;width:min(940px,100%);max-height:92svh;overflow-y:auto;background:#FCFAF5;border-radius:3px;box-shadow:0 40px 90px rgba(14,42,27,.36)">
    <button class="nbh-14" type="button" onclick="NB.closeQuick(event)" aria-label="إغلاق" style="position:absolute;top:12px;inset-inline-start:12px;z-index:3;width:44px;height:44px;border:0;background:rgba(252,250,245,.8);cursor:pointer;display:grid;place-items:center;border-radius:50%">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0E2A1B" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
    </button>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
      <div style="position:relative;background:#F5EBD8;min-height:clamp(260px,32vw,420px);display:grid;place-items:center;padding:30px">
        <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(107,70,48,.14) .5px,transparent .5px);background-size:3px 3px"></div>
        <img :src="$store.quick.img" :alt="$store.quick.name" style="position:relative;max-width:88%;max-height:clamp(220px,28vw,360px);object-fit:contain;filter:drop-shadow(0 24px 32px rgba(14,42,27,.2))"/>
      </div>
      <div style="padding:clamp(26px,3vw,44px)">
        <p style="margin:0 0 10px;font-size:11px;letter-spacing:.24em;color:#6B4630" x-text="$store.quick.cat"></p>
        <h2 style="margin:0 0 14px;font-size:clamp(22px,2.6vw,34px);font-weight:600;letter-spacing:-.01em" x-text="$store.quick.name"></h2>
        <p style="margin:0 0 20px;font-size:22px;font-weight:600;color:#176B3A" x-text="$store.quick.priceLabel"></p>
        <p style="margin:0 0 26px;font-size:15px;line-height:1.95;font-weight:300;color:#3d4a40;text-wrap:pretty" x-text="$store.quick.desc"></p>
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
          <div style="display:flex;align-items:center;border:1px solid rgba(14,42,27,.18);border-radius:2px">
            <button type="button" @click="$store.quick.dec()" aria-label="تقليل الكمية" style="width:48px;height:52px;border:0;background:none;cursor:pointer;font-size:18px;color:#0E2A1B">−</button>
            <span style="min-width:32px;text-align:center;font-size:15px" x-text="$store.quick.qty"></span>
            <button type="button" @click="$store.quick.inc()" aria-label="زيادة الكمية" style="width:48px;height:52px;border:0;background:none;cursor:pointer;font-size:18px;color:#0E2A1B">+</button>
          </div>
          <button class="nbh-7" type="button" @click="$store.quick.addToCart()" style="flex:1 1 180px;border:0;cursor:pointer;background:#0E2A1B;color:#F5EBD8;font-size:15px;font-weight:500;padding:17px 28px;border-radius:2px;min-height:54px">أضف للسلة</button>
          <a :href="$store.quick.url" style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;font-size:13.5px;color:#6B4630;text-decoration:underline;text-underline-offset:4px;padding:17px 6px">عرض كل التفاصيل</a>
        </div>
      </div>
    </div>
  </div>
</div>
