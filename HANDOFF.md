# NABTA — E-commerce Handoff & Backend Migration Plan

Arabic (RTL) storefront, Egyptian market, **guest checkout + Cash on Delivery only**.
This document covers what exists, what was just added, what must happen before the
design is rebuilt on a framework, and the recommended backend stack.

---

## 1. Current state

| File | What it is |
|---|---|
| `NABTA.dc.html` | The home page. A **Design Canvas** file (`<x-dc>` + `support.js`), rendered by React under the hood with `{{ }}` template bindings. **This is a prototyping format, not a production framework.** |
| `categories.html` | Standalone 8-category page. Plain HTML + GSAP. |
| `shop.html` | **NEW** — all products, filterable by category. |
| `product.html` | **NEW** — product detail page (PDP), `?id=<productId>`. |
| `checkout.html` | **NEW** — guest COD checkout form. |
| `order-confirmation.html` | **NEW** — post-order thank-you + order summary. |
| `assets/js/products.js` | **NEW** — catalogue (temporary; replaced by the API). |
| `assets/js/nabta-shop.js` | **NEW** — cart, pricing, shipping, validation, order payload. |
| `assets/js/nabta-chrome.js` | **NEW** — shared header/footer for the new pages. |
| `data/products.json` | **NEW** — catalogue seed/contract for the backend. Generated from `products.js`. |

### What was wired up
- Product card titles on the home page were **buttons opening a modal** — a product had no URL of its own. They are now real links to `product.html?id=…` (linkable, shareable, indexable).
- The cart drawer's *"إتمام الشراء"* button was a **dead end** that only printed *"الدفع غير مُفعّل"*. It now goes to the real checkout.
- Category tiles pointed at a generic `#products` anchor. They now deep-link to `shop.html?cat=<slug>`.
- Quick-view modal gained a *"عرض كل التفاصيل"* link to the PDP.
- The cart is shared across every page via the **same** `localStorage` key (`nabta_cart_v1`) and shape (`[{id, qty}]`) the home page already used, so nothing desyncs.

### Verified working
Filter → add to cart → PDP (qty stepper honours stock) → checkout → validation → order → confirmation → cart cleared. Shipping tiers confirmed (Cairo 50 / Alex 60 / Aswan 90 / Red Sea 100, free ≥ 1000 EGP).

---

## 2. ⚠️ Blockers — fix before or during the migration

### 2.1 Image weight — the single biggest problem
The `-opened.png` files are **2.3–2.8 MB each, ~13 MB total**. They are hover-only decoration.

```
masalek-opened.png        2.6 MB      cinnamon-opened.png   2.8 MB
macadamia-opened.png      2.8 MB      black-honey-opened.png 2.6 MB
pumpkin-seeds-opened.png  2.3 MB
```

Convert to WebP/AVIF at display size (~800px). Expect **>90% reduction**. Also add real
`width`/`height` attributes and `srcset`. Today they are excluded on mobile via CSS so
phones don't pay for them, but that is a mitigation, not a fix.

### 2.2 Product data exists in two places
`NABTA.dc.html` has its own `const PRODUCTS = [...]` **and** `assets/js/products.js` exists.
Prices/names can drift. The migration must collapse both into one API call.

### 2.3 Prices, shipping and totals are computed in the browser
`nabta-shop.js` calculates subtotal, shipping and total client-side. **A customer can edit
these in devtools.** The server must recompute every amount from its own catalogue and
ignore the numbers the client sends (they are included in the payload only so the server can
detect a stale tab and return `409`).

### 2.4 Placeholder content that must come from the client
- `weight`, `stock`, `ingredients`, `usage`, `sku` — invented, realistic stand-ins.
- **Shipping rates and the 1000 EGP free-shipping threshold** — invented. Confirm with the courier.
- The `أعشاب` (herbs) category currently has **no products** mapped to it.
- Footer phone `+20 100 000 0000` is a placeholder.

### 2.5 SEO / rendering
New pages inject header/footer and PDP content with JavaScript. Fine for a prototype,
**not acceptable for production** — a product page must be server-rendered with its own
`<title>`, `<meta description>`, canonical URL, OpenGraph tags and `Product` JSON-LD.

### 2.6 Minor
- `NABTA.dc.html` fires a 404 for `{{ quickImg }}` on every load (the raw template string is
  parsed as an image URL before React binds). Disappears when rebuilt on a framework.
- Everything is inline styles. Extract to design tokens (see §5).
- No 404 page, no privacy/terms/shipping-policy pages (required by payment providers later).

---

## 3. Data model

Generated seed: `data/products.json`.

```
categories   id, slug, name_ar, sort_order, is_active
products     id, slug, sku, name_ar, description_ar, short_desc_ar,
             category_id, price, compare_at_price, currency,
             weight_label, stock, is_active, is_featured,
             ingredients_ar, usage_ar, created_at, updated_at
product_images  id, product_id, url, alt_ar, sort_order
governorates    id, name_ar, shipping_fee, is_active
orders          id, order_number, status, payment_method('cod'),
                customer_name, customer_phone, customer_alt_phone,
                governorate_id, city, address_line, landmark, notes,
                subtotal, shipping_fee, total, currency,
                placed_at, confirmed_at, delivered_at, cancelled_at, cancel_reason
order_items     id, order_id, product_id, sku, name_ar,
                unit_price, qty, line_total      -- snapshot at purchase time
```

**Snapshot the name and price into `order_items`.** If a product's price changes later, old
orders must still show what the customer actually agreed to pay.

### COD order status flow
COD in Egypt has a high refusal/no-answer rate, so the status model matters more than usual:

```
pending → confirmed → shipped → delivered
   ↓          ↓          ↓
cancelled  cancelled  returned      (+ cancel_reason)
```

`pending → confirmed` is the **confirmation phone call**. Track it — it is the main lever
against fake orders. Consider phone OTP at checkout if fake-order volume becomes a problem.

---

## 4. API contract

The frontend already builds and posts exactly this. Keep the field names.

```
GET  /api/products                 → list (supports ?category=<slug>)
GET  /api/products/{idOrSlug}      → single product (404 if missing/inactive)
GET  /api/categories               → list
GET  /api/shipping/governorates    → [{ name, fee }]  ← never hardcode client-side
POST /api/orders                   → place order
```

**`POST /api/orders` request** (produced by `NabtaShop.buildOrderPayload`):

```json
{
  "channel": "web",
  "locale": "ar-EG",
  "currency": "EGP",
  "paymentMethod": "cod",
  "customer":        { "fullName": "…", "phone": "01012345678", "altPhone": null },
  "shippingAddress": { "governorate": "القاهرة", "city": "…", "addressLine": "…", "landmark": null },
  "notes": null,
  "items":   [{ "productId": "honey", "sku": "NBT-HNY-005", "name": "عسل أسود",
                "unitPrice": 95, "qty": 1, "lineTotal": 95 }],
  "amounts": { "subtotal": 1165, "shipping": 0, "total": 1165 }
}
```

**Response** `201` → `{ "orderNumber": "NB-10023", "status": "pending" }`

**Server must:**
1. Re-fetch every product; reject unknown/inactive ids.
2. Recompute `unitPrice`, `lineTotal`, `subtotal`, `shipping`, `total` from its own data.
   If they differ from the client's `amounts`, return `409` with the corrected totals so the
   UI can ask the customer to review.
3. Re-validate **every** rule in `validateOrderForm()` (phone `^01[0125][0-9]{8}$`, etc.).
4. Decrement stock **inside a transaction**; reject if insufficient.
5. Generate `orderNumber` server-side (the client's `NB-XXXXXX` demo fallback is not safe).
6. Rate-limit by IP + phone. A public COD endpoint with no auth **will** get spammed.

**Turning the frontend live:** set `window.NABTA_API = 'https://api.nabta.eg'` before
`nabta-shop.js` loads. Without it the checkout runs in demo mode (validates, builds the real
payload, sends nothing) — the yellow notice on checkout/confirmation says so in Arabic.

---

## 5. Recommended stack

### Backend: **Laravel** — for this project, clearly

| | Laravel | .NET |
|---|---|---|
| Time to ship this scope | **Fastest** | Slower |
| Admin panel | **Filament/Nova ≈ free** — order list, status changes, stock | Build it, or pay for a grid library |
| Egypt hosting | **Cheap, everywhere, cPanel-friendly** | Needs VPS/Azure; pricier |
| Local dev pool | **Very large** — easy to hand over | Smaller for web/e-com |
| Paymob / Fawry (later) | **Mature PHP SDKs & community** | Usable, less community |
| Raw performance | Plenty for this traffic | Higher |
| Best when | Content/CRUD product sites | Complex domains, enterprise/Microsoft shops, heavy compute |

The deciding factor is the **admin panel**. A COD store lives or dies on order operations —
staff calling customers, flipping statuses, adjusting stock. Filament gives you that in days.
In .NET you build it by hand.

**Pick .NET instead if** the client is already a Microsoft shop, has .NET staff, or this is
phase one of something with genuinely complex business logic. Otherwise Laravel.

### Frontend: **Laravel + Inertia + React + Tailwind**
- Keeps the React mental model the design already uses (`support.js` is React underneath).
- **One repo, one deploy** — no CORS, no split hosting. Right call for this size.
- Server-rendered → fixes the SEO gap in §2.5.
- Tailwind replaces the inline styles; port the palette to tokens first:

```
cream #FCFAF5   beige #F5EBD8   deep-green #0E2A1B
green #176B3A   gold  #B08D57   brown #6B4630
muted #3A483D / #4a5a4e / #2C3E30
font: 'IBM Plex Sans Arabic'
```

> Choose **Next.js + Laravel API** only if a mobile app or another storefront will consume
> the same API. It costs you a second deploy and CORS for benefits you don't need yet.

### Keep RTL correct
The current code already uses logical properties (`inset-inline`, `margin-inline`) rather
than `left`/`right`. **Preserve that.** In Tailwind use `ps-*`/`pe-*`/`ms-*`/`me-*`, never
`pl-*`/`pr-*`, and keep `<html dir="rtl" lang="ar">`.

---

## 6. Migration order

1. **Compress the images** (§2.1) — biggest win, independent of everything else.
2. Scaffold Laravel; migrations + seeders from `data/products.json`.
3. Build the API in §4, with server-side recalculation.
4. Install Filament: products, categories, stock, **orders with status transitions**.
5. Port pages to Inertia + React + Tailwind: home → shop → PDP → checkout → confirmation.
   Reuse `nabta-shop.js` logic almost verbatim; delete `products.js` and `nabta-chrome.js`.
6. Add SEO: SSR meta, canonicals, `Product` JSON-LD, sitemap, `robots.txt`.
7. Add policy pages, 404, order-confirmation email/SMS.
8. Analytics: `add_to_cart`, `begin_checkout`, `purchase` (Meta Pixel matters for this market).

## 7. Pre-launch checklist
- [ ] Real product content: weights, stock, ingredients, SKUs, photos
- [ ] Real shipping rates + free-shipping threshold confirmed with the courier
- [ ] Products assigned to the empty `أعشاب` category
- [ ] Real support phone number
- [ ] Order confirmation SMS/WhatsApp (Egyptian customers expect it)
- [ ] Courier integration (Bosta / Mylerz / Aramex) or a manual export
- [ ] Rate limiting + fake-order handling on `POST /api/orders`
- [ ] Privacy policy, terms, shipping & returns pages
- [ ] Lighthouse pass on 3G mobile
