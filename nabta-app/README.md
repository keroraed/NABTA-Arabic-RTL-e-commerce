# NABTA — Laravel storefront + admin

Arabic (RTL) storefront for the Egyptian market. Guest checkout, cash on delivery
only. Laravel 13 + Blade + Alpine, with a Filament admin panel.

This replaces the static prototype in the parent directory. The design is a
**faithful port** of those pages, not a re-interpretation — see
[Design fidelity](#design-fidelity) before changing any markup or CSS.

---

## Running it locally

```bash
composer install
cp .env.example .env && php artisan key:generate
```

Set the database in `.env` (MySQL, to match the Hostinger target):

```
DB_CONNECTION=mysql
DB_DATABASE=nabta
DB_USERNAME=root
DB_PASSWORD=
APP_LOCALE=ar
APP_TIMEZONE=Africa/Cairo
```

Then:

```bash
php artisan migrate --seed
php artisan serve
```

The seeders import the catalogue from `../data/products.json`, the shipping table
from the prototype, and create an admin account — `admin@nabta.test` / `password`.
**Change that password before the site is reachable from anywhere but localhost.**

No `npm install` or build step is needed for the storefront: its CSS and JS are
plain files in `public/`, and the motion libraries load from the same CDNs the
prototype used. (Vite is still wired up for Filament's own assets, which ship
pre-built.)

---

## Layout

```
app/
  Http/Controllers/     ShopController, CartController, CheckoutController
  Services/             Cart, ShippingCalculator, OrderPlacer, OrderNumberGenerator
  Filament/             admin resources, settings page, dashboard widget
  Models/
config/nabta.php        per-product art direction (see below)
public/css/nabta.css    the ported design
public/js/nabta.js      the ported behaviour
resources/views/
  layouts/storefront    page shell
  partials/             shared header/footer, toast
  storefront/           home, shop, product, categories, checkout, confirmation
```

### Storefront routes

| Route | Page |
|---|---|
| `/` | home (the Design Canvas one-pager) |
| `/shop`, `/shop?cat=<slug>` | product listing, filtered |
| `/p/{slug}` | product detail |
| `/categories` | the eight category cards |
| `/checkout`, `/order/{number}` | COD checkout and confirmation |
| `/cart`, `/cart/add/{id}` … | cart endpoints (session + CSRF, JSON for Alpine) |

There is deliberately **no `routes/api.php`**. The POS phase mandates a Blade
monolith with session auth throughout; the cart endpoints answer JSON but are
ordinary web routes.

---

## Where the money is decided

Everything monetary is computed server-side and nothing the browser sends about
prices, fees or totals is trusted or stored. The prototype computed totals in
the browser and posted them, which a customer could edit in devtools
(`HANDOFF.md` §2.3).

- The cart holds **product ids and quantities only**, in the session.
- `Cart::lines()` re-resolves prices and re-clamps quantities to stock on every
  read, so a week-old tab cannot check out at last week's price.
- `OrderPlacer` recomputes every line and total inside one transaction, locks
  each product row, decrements stock, and snapshots name + unit price onto
  `order_items`. Later price edits never rewrite an existing order.
- `ShippingCalculator` is the only source of a shipping fee.
- Order numbers are generated server-side under a row lock.

If stock ran out between adding to the cart and confirming, the order is rejected
whole with an Arabic message rather than silently shipping fewer items.

`tests/Feature/CheckoutTest.php` covers each of these.

---

## POS

A point-of-sale system for the physical shop shares this codebase, this admin
panel and this product catalogue. It is reached at `/pos` and linked from the
admin sidebar.

**The offline layer is not built yet** — that was the scope for this phase, and
it follows the brief's own build order: prove the money and stock maths online
before adding a sync layer on top of them.

Stock is no longer a column on `products`. It is derived from an append-only
movement ledger, which is also what the storefront now reads. Two things follow
from that and are worth knowing before touching stock anywhere:

- `$product->stock` reads the balance in the storefront's fulfilment warehouse.
- Writing to it posts a physical-count adjustment for the difference. Routine
  changes come from sales and goods receipts, not from that field.

Returns are at `/pos/returns`: pull up a receipt, pick what is coming back, and
the refund is calculated at the price actually charged. A return is stored as a
mirrored sale with negated amounts, so revenue, drawer and stock all net out
under a plain `SUM` — see [docs/ROLES.md](docs/ROLES.md#returns).

See [docs/POS.md](docs/POS.md) for the ledger conventions, the scale barcode
configuration that **must be confirmed before go-live**, and what is deliberately
still missing.

---

## Design fidelity

The storefront markup, inline styles and animations were lifted from the static
prototype rather than rebuilt. **The values in `public/css/nabta.css` and the
inline styles in the Blade views are the design spec.** Check any change against
the originals (`../NABTA.dc.html`, `../shop.html`, `../product.html`).

Three things worth knowing before editing:

**Art direction is per-product.** Every product photo is framed by hand — each
pack sits at a different height and width inside its card, because the source
images are cropped differently. Those values live in `config/nabta.php`, keyed by
the product's `legacy_id`, with a default for anything added later through the
admin. Flattening them to one rule visibly breaks the layout.

**The category shelf breakout is one timeline per tile.** `TILE_MOTION` in
`public/js/nabta.js` is copied verbatim from the prototype: each category behaves
like a different physical object, and the peak/settle keyframes are what make the
overshoot read as weight rather than a bounce easing. On touch devices the same
timeline is driven by scroll position instead of hover.

**Two things the prototype annotated but never ran.** Design Canvas treated
`style-hover` as an editor-only hint, so those hover states were dead on the live
page; they are real CSS now (`nabta.css` §3), matching what `shop.html` and
`product.html` already did. And `isDesktop`/`isMobile` were React state; they are
media queries now, so there is no flash of the wrong variant on first paint.

The one deliberate content change: the prototype's *"الأسعار المعروضة تجريبية"*
disclaimers are gone, since the catalogue is now real.

---

## Admin

`/admin`, Filament v5, Arabic and RTL out of the box.

- **المنتجات** — full CRUD, image repeater, in-line stock/visibility toggles,
  low-stock filter.
- **الأقسام** — CRUD plus the landing-page copy (heading, badge, description)
  that used to be hard-coded in `categories.html`.
- **الطلبات** — read-only, split into tabs by status, with explicit transition
  actions. See below.
- **المحافظات والشحن** — the shipping table, editable in place.
- **إعدادات المتجر** — free-shipping threshold, support phone, address.
- **المستخدمون** — staff accounts, roles and per-user permissions.

### Orders are read-only on purpose

An order records what a customer agreed to pay, and its totals were computed and
snapshotted at checkout. Letting staff retype an amount or a line item would
silently break the only guarantee checkout makes, so `OrderResource` disables
create and edit entirely.

Status moves through explicit actions that follow the COD flow:

```
pending → confirmed → shipped → delivered
   ↓          ↓           ↓
cancelled  cancelled   returned
```

`pending → confirmed` is the confirmation phone call — the main lever against
fake COD orders, so it is a real transition, not a formality. Each action is
visible only where the current status allows it, re-checks on write in case the
row moved on in another tab, and stamps its own timestamp column. Cancelling or
returning requires a reason.

Every row in `users` is shop staff: there is no public sign-up anywhere in the
app.

### Staff, roles and languages

Three roles — **مدير** (everything), **محاسب** (stock, accounts, reports,
orders) and **كاشير** (the till and returns only). A cashier cannot open the
admin panel at all; signing in sends them straight to `/pos`.

Permissions are ordinary Laravel Gate abilities, so the app checks them with
`can()` like anything else. Any one user can override their role's defaults
without inventing a new role, and an active admin always passes every check —
including permissions added by a later migration.

The **admin panel and POS are bilingual** (Arabic default, English in the user
menu). The storefront is Arabic only.

See [docs/ROLES.md](docs/ROLES.md) for the permission list, the returns design,
and the two translation traps worth knowing before adding a navigation item.

---

## Images

Product images live on the `assets` disk — `public/assets` — so a seeded image
and one uploaded through the admin resolve identically, and **no `storage:link`
is involved**. That symlink is a recurring source of broken images on shared
hosting, which is the deployment target.

Paths are stored relative to that disk (`products/masalek.webp`) and the URL is
host-relative, so it survives any domain, port or `APP_URL` drift.

---

## Tests

```bash
php artisan test
```

98 feature tests over the cart, checkout money/stock rules, the admin panel, POS
pricing and sales, returns, accounting, and roles/permissions. They run against
SQLite in memory; nothing touches the MySQL database.

---

## Still open before launch

Carried over from `../HANDOFF.md` §2.4 and §7 — these are content and business
decisions, not code:

- [ ] **Shipping rates and the 1000 EGP free-shipping threshold are invented.**
      Confirm with the courier, then correct them in admin (المحافظات والشحن /
      إعدادات المتجر).
- [ ] **Stock levels are invented placeholders** (round numbers: 24, 18, 40 …).
      Do a physical count and enter the real figures before go-live.
- [ ] Real support phone number — currently `+20 100 000 0000`.
- [ ] The `أعشاب` category still has no products mapped to it, so its card links
      to an empty listing.
- [ ] Product weights, ingredients, usage text and SKUs are realistic stand-ins,
      not client-supplied.
- [ ] Order confirmation SMS/WhatsApp — Egyptian customers expect one.
- [ ] Privacy, terms and shipping/returns pages.
- [ ] Courier integration (Bosta / Mylerz / Aramex) or a manual CSV export.
- [ ] Analytics: `add_to_cart`, `begin_checkout`, `purchase` (Meta Pixel matters
      in this market).

## Not done in this phase

- The `-opened.png` originals in `../assets/products/` are still 2.3–2.8 MB each.
  The `.webp` conversions are what the app serves, but the PNGs are dead weight
  in git.
- No sitemap or `robots.txt` yet. Product pages do have server-rendered titles,
  meta descriptions, canonicals, OpenGraph tags and `Product` JSON-LD.
- Deployment to Hostinger is not scripted: `public_html` needs to point at
  `public/`, and a cron entry for `schedule:run` should be added when anything
  starts needing it.
