/* ============================================================================
   NABTA — shared commerce layer (cart, pricing, shipping, order submission)
   ----------------------------------------------------------------------------
   Guest checkout only. No accounts, no login. Cash on delivery (COD) only.

   Cart is stored in localStorage under the SAME key the existing one-pager uses
   ('nabta_cart_v1') and in the SAME shape ([{id, qty}]), so the home page cart
   drawer and these new pages stay in sync automatically.

   MIGRATION NOTES for the backend team are marked with  →API
   ========================================================================== */
(function (w) {
  'use strict';

  var CART_KEY = 'nabta_cart_v1';       // must match NABTA.dc.html
  var ORDER_KEY = 'nabta_last_order';   // demo-mode only, see submitOrder()

  /* --- endpoint -----------------------------------------------------------
     Set window.NABTA_API = 'https://api.nabta.example' before this script to
     make checkout POST for real. If unset, checkout runs in DEMO MODE: it
     validates everything, builds the exact payload, stores it locally and
     forwards to the confirmation page — but nothing is actually sent.        */
  var API = w.NABTA_API || null;

  /* --- money --------------------------------------------------------------
     →API All money is handled in whole EGP here because the current catalogue
     has no fractional prices. If the backend introduces piastres, switch this
     and the catalogue to integer minor units (piastres) to avoid float bugs. */
  function fmt(n) {
    return Number(n).toLocaleString('en-US') + ' ج.م';
  }

  /* --- catalogue ---------------------------------------------------------- */
  function products() { return w.NABTA_PRODUCTS || []; }
  function categories() { return w.NABTA_CATEGORIES || []; }
  function find(id) {
    return products().filter(function (p) { return p.id === id; })[0] || null;
  }
  function inStock(p) { return !!p && p.stock > 0; }

  /* --- cart --------------------------------------------------------------- */
  function getCart() {
    try {
      var raw = localStorage.getItem(CART_KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.filter(function (l) { return l && l.id && l.qty > 0; }) : [];
    } catch (e) { return []; }
  }
  function saveCart(cart) {
    try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); } catch (e) {}
    w.dispatchEvent(new CustomEvent('nabta:cart', { detail: { cart: cart } }));
  }
  function addToCart(id, qty) {
    qty = Math.max(1, parseInt(qty, 10) || 1);
    var p = find(id);
    if (!p) return getCart();
    var cart = getCart();
    var i = -1, k;
    for (k = 0; k < cart.length; k++) { if (cart[k].id === id) { i = k; break; } }
    var next = (i > -1 ? cart[i].qty : 0) + qty;
    // never let the cart exceed available stock
    next = Math.min(next, p.stock);
    if (i > -1) cart[i] = { id: id, qty: next }; else cart.push({ id: id, qty: next });
    saveCart(cart);
    return cart;
  }
  function setQty(id, qty) {
    var p = find(id);
    var cart = getCart().map(function (l) {
      if (l.id !== id) return l;
      var n = Math.max(0, parseInt(qty, 10) || 0);
      if (p) n = Math.min(n, p.stock);
      return { id: id, qty: n };
    }).filter(function (l) { return l.qty > 0; });
    saveCart(cart);
    return cart;
  }
  function removeFromCart(id) {
    var cart = getCart().filter(function (l) { return l.id !== id; });
    saveCart(cart);
    return cart;
  }
  function clearCart() { saveCart([]); }
  function cartCount() {
    return getCart().reduce(function (s, l) { return s + l.qty; }, 0);
  }
  /* Resolves cart lines against the catalogue. Lines whose product no longer
     exists are dropped rather than crashing the page. */
  function cartLines() {
    return getCart().map(function (l) {
      var p = find(l.id);
      if (!p) return null;
      return { product: p, qty: l.qty, lineTotal: p.price * l.qty };
    }).filter(Boolean);
  }
  function subtotal() {
    return cartLines().reduce(function (s, l) { return s + l.lineTotal; }, 0);
  }

  /* --- shipping -----------------------------------------------------------
     ⚠️ PLACEHOLDER RATES — the client has not supplied a real shipping table.
     Confirm with the courier before launch.
     →API These rates must move server-side. Never trust a shipping fee that
     came from the browser: recalculate it on the server when creating the
     order, or a customer can edit the total in devtools.                      */
  var SHIPPING = [
    { gov: 'القاهرة', fee: 50 }, { gov: 'الجيزة', fee: 50 }, { gov: 'القليوبية', fee: 55 },
    { gov: 'الإسكندرية', fee: 60 }, { gov: 'البحيرة', fee: 65 }, { gov: 'مطروح', fee: 90 },
    { gov: 'دمياط', fee: 65 }, { gov: 'الدقهلية', fee: 65 }, { gov: 'الشرقية', fee: 65 },
    { gov: 'كفر الشيخ', fee: 65 }, { gov: 'الغربية', fee: 65 }, { gov: 'المنوفية', fee: 65 },
    { gov: 'بورسعيد', fee: 70 }, { gov: 'الإسماعيلية', fee: 70 }, { gov: 'السويس', fee: 70 },
    { gov: 'شمال سيناء', fee: 100 }, { gov: 'جنوب سيناء', fee: 100 },
    { gov: 'بني سويف', fee: 75 }, { gov: 'الفيوم', fee: 75 }, { gov: 'المنيا', fee: 75 },
    { gov: 'أسيوط', fee: 80 }, { gov: 'سوهاج', fee: 80 }, { gov: 'قنا', fee: 85 },
    { gov: 'الأقصر', fee: 85 }, { gov: 'أسوان', fee: 90 },
    { gov: 'البحر الأحمر', fee: 100 }, { gov: 'الوادي الجديد', fee: 100 }
  ];
  var FREE_SHIPPING_OVER = 1000; // ⚠️ PLACEHOLDER — confirm with client
  function governorates() { return SHIPPING.map(function (r) { return r.gov; }); }
  function shippingFee(gov) {
    if (subtotal() >= FREE_SHIPPING_OVER) return 0;
    var row = SHIPPING.filter(function (r) { return r.gov === gov; })[0];
    return row ? row.fee : 0;
  }
  function total(gov) { return subtotal() + shippingFee(gov); }

  /* --- validation ---------------------------------------------------------
     →API Mirror EVERY rule below on the server. Client validation is for the
     customer's convenience only; it is trivially bypassed.                    */
  var EG_PHONE = /^01[0125][0-9]{8}$/;
  function validateOrderForm(f) {
    var errors = {};
    if (!f.fullName || f.fullName.trim().length < 3) errors.fullName = 'من فضلك اكتب الاسم بالكامل';
    if (!EG_PHONE.test(String(f.phone || '').trim())) errors.phone = 'رقم موبايل مصري غير صحيح (مثال: 01012345678)';
    if (f.altPhone && String(f.altPhone).trim() && !EG_PHONE.test(String(f.altPhone).trim())) errors.altPhone = 'رقم الموبايل البديل غير صحيح';
    if (!f.governorate) errors.governorate = 'اختر المحافظة';
    if (!f.city || f.city.trim().length < 2) errors.city = 'اكتب المدينة أو المنطقة';
    if (!f.address || f.address.trim().length < 10) errors.address = 'اكتب العنوان بالتفصيل (شارع، رقم العقار، الدور)';
    return { valid: Object.keys(errors).length === 0, errors: errors };
  }

  /* --- order --------------------------------------------------------------
     This is THE API CONTRACT. Backend: accept exactly this shape.
     →API The server must IGNORE the client's prices/fees/totals and recompute
     them from its own catalogue. They are included only so the server can
     detect a price mismatch (e.g. the customer had a stale tab open) and
     respond 409 so the UI can ask them to review the new total.               */
  function buildOrderPayload(f) {
    var lines = cartLines();
    var gov = f.governorate;
    return {
      channel: 'web',
      locale: 'ar-EG',
      currency: 'EGP',
      paymentMethod: 'cod',              // only method supported right now
      customer: {
        fullName: String(f.fullName).trim(),
        phone: String(f.phone).trim(),
        altPhone: f.altPhone ? String(f.altPhone).trim() : null
      },
      shippingAddress: {
        governorate: gov,
        city: String(f.city).trim(),
        addressLine: String(f.address).trim(),
        landmark: f.landmark ? String(f.landmark).trim() : null
      },
      notes: f.notes ? String(f.notes).trim() : null,
      items: lines.map(function (l) {
        return {
          productId: l.product.id,
          sku: l.product.sku,
          name: l.product.name,
          unitPrice: l.product.price,
          qty: l.qty,
          lineTotal: l.lineTotal
        };
      }),
      amounts: {
        subtotal: subtotal(),
        shipping: shippingFee(gov),
        total: total(gov)
      }
    };
  }

  /* Returns a Promise resolving to { orderNumber, demo }.
     →API Expected success response: { orderNumber: "NB-10023", status: "pending" }
     The order number MUST be generated server-side. The demo fallback below is
     NOT safe for production — it can collide and is client-controlled.        */
  function submitOrder(payload) {
    if (!API) {
      var demoNumber = 'NB-' + Date.now().toString(36).toUpperCase().slice(-6);
      var record = { orderNumber: demoNumber, placedAt: new Date().toISOString(), payload: payload };
      try { sessionStorage.setItem(ORDER_KEY, JSON.stringify(record)); } catch (e) {}
      return Promise.resolve({ orderNumber: demoNumber, demo: true });
    }
    return fetch(API.replace(/\/$/, '') + '/api/orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept-Language': 'ar-EG' },
      body: JSON.stringify(payload)
    }).then(function (res) {
      if (!res.ok) return res.json().catch(function () { return {}; }).then(function (b) {
        throw new Error(b.message || 'تعذّر إرسال الطلب، حاول مرة أخرى');
      });
      return res.json();
    }).then(function (data) {
      try {
        sessionStorage.setItem(ORDER_KEY, JSON.stringify({
          orderNumber: data.orderNumber, placedAt: new Date().toISOString(), payload: payload
        }));
      } catch (e) {}
      return { orderNumber: data.orderNumber, demo: false };
    });
  }
  function lastOrder() {
    try { return JSON.parse(sessionStorage.getItem(ORDER_KEY) || 'null'); } catch (e) { return null; }
  }

  /* --- small DOM helper used by every page's header badge ----------------- */
  function bindCartBadge(sel) {
    function paint() {
      var n = cartCount();
      Array.prototype.forEach.call(document.querySelectorAll(sel), function (el) {
        el.textContent = n;
        el.style.display = n > 0 ? '' : 'none';
      });
    }
    paint();
    w.addEventListener('nabta:cart', paint);
    w.addEventListener('storage', paint);
  }

  w.NabtaShop = {
    fmt: fmt,
    products: products, categories: categories, find: find, inStock: inStock,
    getCart: getCart, addToCart: addToCart, setQty: setQty, removeFromCart: removeFromCart,
    clearCart: clearCart, cartCount: cartCount, cartLines: cartLines,
    subtotal: subtotal, shippingFee: shippingFee, total: total,
    governorates: governorates, freeShippingOver: FREE_SHIPPING_OVER,
    validateOrderForm: validateOrderForm, buildOrderPayload: buildOrderPayload,
    submitOrder: submitOrder, lastOrder: lastOrder,
    bindCartBadge: bindCartBadge,
    isDemo: !API
  };
})(window);
