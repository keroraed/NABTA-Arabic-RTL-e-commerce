# NABTA POS — implementation notes

Companion to `../NABTA-POS-Scale-Offline-Prompt.md` and
`../NABTA-POS-Offline-Design.md`. This documents what is **built**, the
decisions taken along the way, and what is deliberately **not** built yet.

**The offline layer is not implemented.** That was the explicit scope for this
phase, and it follows the brief's own build order: prove the money and stock
maths online first, because debugging financial logic through a sync layer is
much harder than debugging it directly.

---

## What exists

| Layer | Status |
|---|---|
| Warehouses + append-only stock ledger | Built |
| Party ledger (customers + suppliers) | Built |
| Treasury (cash accounts + movements) | Built |
| POS terminals, cash shifts | Built |
| Till screen: scan, cart, cash/credit/partial, receipt | Built |
| Weight barcode parser (PHP + JS mirror) | Built |
| Idempotent sale registration | Built |
| PLU export for the scale (Phase 1) | Built |
| Party statement + treasury reports | Built |
| Returns / refunds | Built |
| Roles, permissions, staff accounts | Built |
| Arabic / English admin and till | Built |
| Offline PWA, service worker, Dexie, outbox | **Not built** |
| Purchases / goods receipt UI | Schema only |
| Stock transfers UI | Schema only |
| Bridge agent (Phase 2 direct scale push) | Not built |

---

## The three ledgers

Nothing in this system stores a balance as the source of truth. Three
append-only tables record what happened; every balance is derived from them.

| Table | Written by | Derives |
|---|---|---|
| `stock_movements` | `Services\Inventory\StockLedger` | stock on hand, per warehouse |
| `ledger_entries` | `Services\Accounting\PartyLedger` | what a customer or supplier owes |
| `cash_movements` | `Services\Accounting\Treasury` | what is in each drawer |

All three models **throw on update and delete**. A correction is a movement in
the opposite direction, never an edit — otherwise a statement printed yesterday
stops matching the same statement printed today.

`stock_levels` is a *cache* of the stock ledger, updated in the same transaction
as the movement using an atomic in-database increment (not read-modify-write,
which would drop writes when two terminals sync at once).

```bash
php artisan stock:rebuild          # replay the ledger into the cache
php artisan stock:rebuild --check  # report drift without writing
```

`--check` is safe to run on a schedule as a canary.

### The debit/credit convention

Stated once here and enforced in `PartyLedger`:

- **مدين / debit** — the party owes us less, or we paid them
- **دائن / credit** — we owe them more
- supplier balance owed by us = `SUM(credit) − SUM(debit)`
- customer balance owed to us = `SUM(debit) − SUM(credit)`

So a credit sale **debits** the customer. A goods receipt **credits** the
supplier. Positive always means "money is owed", in whichever direction the
party type implies, so the admin renders one column without branching.

Two columns rather than one signed column, because that is the shape the Arabic
statement wants with no conversion at render time.

---

## `products.stock` is gone

The column was migrated into opening `adjustment` movements and dropped
(`2026_09_06_100015_migrate_product_stock_into_ledger`).

`$product->stock` still **reads** naturally, but it now means "on hand in the
warehouse the storefront sells from", not one global number. Use
`->withOnlineStock()` on a listing query to avoid a query per row.

Writing to `$product->stock` posts a **physical-count adjustment for the
difference**. That is a real operation, not a shim for convenience: it is what a
stock count is. Routine changes should come from sales and goods receipts, not
from retyping that field.

### Storefront vs POS: opposite stock rules, on purpose

- **The POS never blocks a sale.** The customer is at the counter holding the
  goods. Balances may go negative and self-correct on the next goods receipt.
  There is no reservation system and no safety buffer, by explicit instruction.
- **The storefront does block.** Nobody is holding the goods yet, and promising
  something that is not there costs a delivery run.

---

## The scale

### Barcode format — CONFIRM BEFORE GO-LIVE

Everything is driven by `config/pos.php`, which reads env, so confirming the
real format is a one-line change and never a code change.

The current defaults come from **one** label sample taken from the shop:

```
103100100  ->  PLU 1031, 00100 = 100 g
```

```
POS_BARCODE_PREFIX_LENGTH=0
POS_BARCODE_PLU_LENGTH=4
POS_BARCODE_VALUE_LENGTH=5
POS_BARCODE_VALUE_TYPE=weight_grams     # or price_piastres
POS_BARCODE_HAS_CHECK_DIGIT=false
```

**Before writing any real sale, go to the shop and:** print three labels at
different weights, scan them into Notepad to capture what the HID scanner
actually types, and photograph the scale's barcode-format screen. A 4-digit PLU
also caps you at 10,000 codes against a scale rated for 12,000.

The parser is mirrored in `public/js/pos/barcode.js`. Both read the same config,
injected from Blade. **Change one, change the other**, and both test suites.

### Rounding — CONFIRM BEFORE GO-LIVE

The scale prints its own price on the sticker. If the POS rounds differently,
the receipt disagrees with the label in the customer's hand, at the counter, in
front of a queue.

```
POS_ROUNDING_STEP=1     # piastres: 1 = nearest piastre, 5, 25 = quarter pound
```

Confirm the scale's rounding and match it.

### PLU export (Phase 1)

`/reports/plu-export`, linked in the admin sidebar, produces a CSV of products
where `is_weighted` and `sync_to_scale` are both set. Staff import it with the
ACLAS PC software.

**Confirm the column order against that import screen before the first real
export** — `PluExporter::columns()` is a reasonable guess. Getting it wrong
prices goods incorrectly on the scale, which is worse than not exporting.

Phase 2 (direct TCP/RS-232 push from a bridge agent) needs the vendor protocol
from the distributor and is deliberately not blocking Phase 1.

---

## Recording a sale

`Services\Pos\SaleRegistrar` is the only way a POS sale is written. It
guarantees three things:

1. **Atomicity.** Sale, items, stock movements, ledger entry and cash movement
   commit together or not at all. A sale that commits without its stock movement
   is worse than one that fails, because nothing will ever tell you.
2. **Idempotency on `uuid`.** Enforced by the unique index *and* by catching its
   violation — not by a `SELECT` check, because two concurrent requests both
   pass one. This is what lets the offline outbox retry safely later.
3. **Server-side recalculation.** Every line is recomputed from the product's
   stored price. A mismatch is flagged (`has_total_discrepancy`, filterable in
   admin) but **never rejects the sale** — the customer already paid and left.

Money is integer piastres throughout, in PHP (`PosPricing`) and in JS
(`public/js/pos/till.js`). Float money would surface as a drawer a few piastres
short once a week with nothing to trace it to.

`ServicesPosReturnRegistrar` is its mirror image and makes the same three
guarantees. A return is a `pos_sales` row with every amount and quantity
negated, which is what keeps every existing aggregate correct without teaching
it about returns. The rules it enforces — refund at the original price, never
more than is still outstanding, proportional line discounts, credit sales
refunded to the account rather than the drawer — are documented in
[ROLES.md](ROLES.md#returns).

### Receipt numbers

`{terminal_code}-{6 digits}`, e.g. `T1-000145`, sequenced **per terminal** so two
terminals can never collide. Allocated server-side today under a row lock; the
offline client will generate them instead, and the unique index on
`(pos_terminal_id, receipt_number)` catches a clash either way — which is what
happens if a terminal's local storage is ever wiped.

---

## Using the till

1. `/pos` — pick a terminal (saved in the session for that PC)
2. Open a shift with the opening float. **No sale can be recorded without an
   open shift**, so every sale is reconcilable at the end of the day.
3. Scan, or search by Arabic name / SKU / PLU, or click a tile.
   - Scanning a weight label prices it automatically.
   - Picking a weighted product by hand prompts for the weight — for a smudged
     or missing label, which happens.
   - Unit lines merge on rescan; **weighted lines never do**, because two labels
     for the same product carry two different weights.
4. Pay: cash, آجل (credit), or partial. Credit requires a customer.
   The customer's balance is shown but **never blocks the sale** — extending
   more credit is the operator's call.
5. Close the shift: counted vs expected, and the variance is stored, not
   corrected away.

Credit portions are excluded from expected cash — no money changed hands.

To take something back: **مرتجع** in the till header (needs `pos.refund`), then
the receipt number. The screen shows only what is still returnable on that
receipt and previews the refund before anything is written.

---

## Open questions, and what was assumed

The brief lists questions to raise rather than assume. These were **not**
answered, so defensible defaults were taken and the schema was shaped so
changing them later is not a migration against live sales data:

| Question | Assumed | Cost of changing later |
|---|---|---|
| **Egyptian e-invoicing (ETA)** | Not required | **High.** If required, the POS must be online at the moment of sale and the whole offline design changes. **Answer this before building the offline layer.** |
| **VAT** | None applied | Low — `pos_sales.tax_amount` exists and is zero. |
| Returns/refunds this phase | Not built | Low — `type` and `original_sale_id` columns exist. |
| Number of terminals | Any number supported | None. |
| Number of warehouses | Any number supported; one seeded | None. |
| Purchase vs selling units | Supported | None — `purchase_items.unit_factor`. |
| Cost tracking / profit | Weighted average | Low — `average_cost` and `cost_at_sale` are captured but no profit report is built. |
| Multiple branches | Supported via warehouses + cash accounts | None. |

ETA is the one that genuinely matters. It is the reason to ask before the
offline layer, not after.

---

## Tests

```bash
php artisan test
```

Covering, specifically:

- **`PosPricingTest`** — the money maths in isolation, including the real
  240 EGP/kg × 100 g = 24.00 label from the shop, the 15 kg boundary, rounding
  steps, and a discount that can never drive a total below zero.
- **`WeightBarcodeParserTest`** — the sample label, a boundary weight, a plain
  EAN falling through to a unit lookup, malformed input, zero weight, and proof
  the format is driven entirely by config (including an EAN-13 layout).
- **`PosSaleTest`** — cash, weighted, credit and partial sales each moving the
  right ledgers; **replaying the same UUID creating nothing twice**; a failed
  sale leaving no partial record; negative stock allowed; a wrong client total
  flagged but not rejected; and `stock:rebuild` reproducing the cache exactly.
- **`AccountingTest`** — the debit/credit convention, a statement whose closing
  balance equals the sum of its entries, a running balance, treasury
  reconciliation, and append-only enforcement.
- **`PosReturnTest`** — full, partial and weighted returns; the refund using the
  original price rather than today's; a proportional discount refund; a credit
  sale refunding to the account instead of the drawer; over-returning refused;
  a return of a return refused; UUID replay; and a shift that now expects less
  cash in the drawer.
- **`RolesAndPermissionsTest`** — what each role can and cannot reach, a cashier
  locked out of the admin panel, per-user overrides in both directions, a
  deactivated user losing everything including admin, unknown permissions
  ignored, and both language files covering every role and permission label.

---

## When the offline layer is built

The server side is already shaped for it — this is why the online path uses a
client-generated UUID and an idempotent registrar rather than a plain insert.
What remains is client-side plus a small number of sync routes:

- `POST /pos/sync/sales` — batch upload. `SaleRegistrar::register()` is already
  idempotent and already returns the existing sale on replay; the route needs to
  wrap it in a per-sale result array so one bad sale does not fail the batch.
- `GET /pos/sync/catalog`, `GET /pos/sync/customers` — delta sync by cursor.
- Dexie stores, the outbox, the service worker, and the operator health UI.

Two things from the design document to keep in mind, both of which bite in real
shops rather than in testing:

- **The CSRF token cannot come from a precached app shell.** It goes stale and
  every sync 419s, which looks exactly like a session bug. Fetch a fresh token
  from a network-only route instead.
- **`SESSION_LIFETIME` must be raised** (30 days or so). The default of 120
  minutes means a terminal offline for a shift has an expired session before it
  ever gets to sync — so the "session expired" banner becomes the normal
  outcome of a normal day.
