# Roles, permissions and languages

## The three roles

| Role | Sees | Lands on |
|---|---|---|
| **مدير / Administrator** | Everything | Admin dashboard |
| **محاسب / Accountant** | Stock, accounts, treasury, expenses, reports, online orders | Admin dashboard |
| **كاشير / Cashier** | The till and returns, nothing else | `/pos` |

A cashier has no reason to open the admin panel — it would be an empty shell —
so `User::canAccessAdminPanel()` returns false for them and Filament refuses
entry. `User::homeRoute()` sends them to the till instead.

## Permissions

Defined once in `App\Enums\Permission`, turned into Laravel Gate abilities by
`AuthServiceProvider`, so the whole app uses the ordinary `can()` / `@can`
vocabulary rather than a bespoke check.

| Permission | Grants |
|---|---|
| `pos.use` | Open the till, make sales, open/close shifts |
| `pos.refund` | Process returns |
| `products.view` / `products.manage` | Catalogue |
| `orders.manage` | Online orders |
| `inventory.view` / `inventory.manage` | Stock, warehouses, movements |
| `accounting.view` / `accounting.manage` | Parties, treasury, expenses, payments |
| `reports.view` | Statements and treasury reports |
| `users.manage` | Staff accounts (admin only in practice) |
| `settings.manage` | Store settings, governorates, tills |

Deliberately coarse — one entry per area, not one per button. A permission list
nobody can hold in their head is one nobody configures correctly.

### Two guarantees worth knowing

**An admin is never locked out.** `Gate::before` grants an active admin
everything, including permissions added by a future migration they have not seen
yet. Adding a `Permission` case can never accidentally lock the owner out of
their own shop.

**Deactivating beats everything.** `is_active = false` fails every check, admin
included. That is how a departing employee is stopped without deleting rows that
sales and ledger entries point at.

### Per-user overrides

`users.permissions` is normally `NULL`, meaning "whatever the role says". Setting
it to an explicit array overrides the role for that one person — which is how you
give a trusted cashier stock visibility, or take refunds away from a new one,
without inventing a fourth role.

The user form handles this with a **استخدام صلاحيات الدور الافتراضية** toggle.
On = store `NULL`; off = store the ticked list. Changing the role resets the
checkboxes to that role's defaults so the form never shows a stale set.

Unknown values in the array are ignored, so a permission removed from the code
cannot resurrect itself from an old row.

---

## Returns

`/pos/returns`, gated on `pos.refund` separately from `pos.use` — a cashier can
be trusted to sell without being trusted to hand money back out of the drawer.

### How a return is stored

A return is another `pos_sales` row with `type = 'return'`, `original_sale_id`
set, and **every monetary and quantity field negated**.

That is the whole design decision, and it is what keeps the rest of the system
simple:

```
SUM(total)      across sales and returns = net revenue
SUM(paid_cash)                           = what should be in the drawer
SUM(quantity)   per product              = net units sold
```

Storing returns as positive numbers with a type flag would mean every aggregate
in every report had to remember to branch on that flag — and the one that forgot
would overstate revenue silently. The receipt template flips the sign back for
printing, so the customer never sees a page of minus signs.

Stock moves the other way: goods come back in as a `customer_return` movement.

### The rules it enforces

- **Refunds use the price actually charged**, from `unit_price_snapshot`, never
  today's price. A price rise between sale and return must not become free money
  for either side.
- **Never more than is still outstanding.** `returnableQuantities()` subtracts
  everything already returned against each specific line, re-read under a row
  lock inside the transaction so two cashiers cannot both refund the last unit.
  Lines are matched by `original_item_id`, not by product — a receipt can carry
  the same product twice at two different weights, which is normal here.
- **A discounted line refunds proportionally.** Returning one of four items that
  carried a 40 EGP line discount hands back 90, not 100.
- **A credit sale refunds to the account**, not the drawer. Handing over cash
  would pay the customer money they never gave us.
- **A return cannot itself be returned.**
- **Idempotent on `uuid`**, exactly like a sale, so the offline layer can retry.

`tests/Feature/PosReturnTest.php` covers each of these.

---

## Languages

The **admin panel and the POS are bilingual** (Arabic default, English
available). The **storefront is Arabic only** — customers are not offered a
language switch, and `layouts/storefront.blade.php` is hardcoded `dir="rtl"`.

Switching is in the Filament user menu. The choice is stored in the session and
applied by `App\Http\Middleware\SetLocale`.

### How the translations are organised

Two mechanisms, on purpose:

- **`lang/en.json`** — for the admin panel. The Arabic string is the key, so
  Arabic needs no file at all (a missing key falls back to the key). ~210 entries.
- **`lang/{ar,en}/*.php`** — keyed files for the POS, roles, permissions, users
  and errors, where the same concept appears in many places and a stable key is
  worth having.

If you change an Arabic label in the code, change the matching key in
`lang/en.json` too, or that string quietly reverts to Arabic in the English UI.

### One trap, already hit

The Filament panel is constructed **once at boot**, before the locale middleware
runs. A bare `__()` in `AdminPanelProvider` freezes whichever language the app
started in. Navigation labels and groups therefore pass **closures**
(`fn () => __('…')`), which resolve per request. If you add a navigation item and
its label is stuck in one language, this is why.

`SetLocale` is also listed explicitly in the panel's own middleware array:
Filament panel routes do not run the `web` middleware group, so appending it
there alone does nothing.

---

## Adding a role or permission

1. Add the case to `App\Enums\Permission` (and `Role` if it is a new role).
2. Add its defaults in `Role::defaultPermissions()`.
3. Add labels to `lang/ar/permissions.php` and `lang/en/permissions.php` —
   `RolesAndPermissionsTest` fails if either is missing.
4. Gate whatever it protects: `canViewAny()` on a Filament resource, or
   `->middleware('permission:your.permission')` on a route.

Existing admins pick it up automatically through `Gate::before`.
