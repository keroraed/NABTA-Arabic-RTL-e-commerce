# Project Brief: NABTA POS + Weighing Scale Integration (Offline-First)

## Context

This extends the existing **NABTA Laravel e-commerce project** (Arabic/RTL, guest checkout, COD, admin panel) with a **Point of Sale system** for the physical store, integrated with an **ACLAS LS6 barcode label scale**.

The Laravel application is hosted on **Hostinger shared hosting**. The store's internet connection is unreliable, so **the POS must work fully offline and sync when connectivity returns.** This is a hard requirement, not a nice-to-have.

### Hardware in the store
- **ACLAS LS6 barcode label scale** — 15/30kg capacity, e=5/10g, stores up to 12,000 PLUs, has Ethernet (RJ45) and RS-232 ports, built-in thermal label printer.
- **Barcode scanner** — operates as an HID keyboard (no driver needed; it types the scanned digits).
- **A local PC/mini-PC in the store** — runs the POS browser client and the scale bridge agent.
- **Receipt printer** — ESC/POS thermal printer (assume USB or network).

### How the scale works
The staff places a product on the scale, selects the PLU, and the scale prints a label with an embedded barcode. Example real label from the store:

```
Product: لب سورى فاخر
Barcode: 103100100
Weight:  0.100 kg
Price:   24.00
```

The last 5 digits (`00100`) encode the **weight in grams**; the preceding digits encode the **PLU code**. The price is NOT embedded — the POS must look up the product by PLU, read the weight from the barcode, and calculate `price_per_kg × weight`.

**IMPORTANT:** This format is inferred from a single label sample. Before implementing, confirm the actual configured barcode format from the scale's settings menu, and build the parser so the format is **configurable**, not hardcoded.

---

## Architecture Required

Three layers:

```
Laravel on Hostinger (source of truth)
  ├─ E-commerce storefront (existing)
  ├─ Admin panel (existing) + POS/PLU management (new)
  └─ Sync routes in web.php (new)
              ↕ HTTPS (intermittent)
Local layer in the store
  ├─ POS client — offline-first PWA in the browser
  └─ Bridge agent — pushes PLUs to the scale, handles receipt printing
              ↕ LAN (TCP) / RS-232
        ACLAS LS6 scale
```

---

## Architecture Constraint: Blade Monolith, No API Layer

This is a **single Laravel monolith rendered with Blade.** Follow this strictly:

- **No `routes/api.php`.** Every route lives in `routes/web.php`.
- **No Laravel Sanctum, no Passport, no token-based auth.** Standard session authentication with CSRF protection everywhere.
- **No SPA, no React, no Vue, no Inertia.** All screens — admin, POS, reports, statements — are Blade views. Use **Alpine.js** for interactivity where needed.
- **No API Resources, no API versioning, no separate API controllers.** Regular Laravel controllers returning Blade views.

### The one necessary exception — offline sync plumbing

The offline requirement (Part 3) cannot be satisfied with HTML page responses: the background sync process must send stored sales and receive structured data. Implement this as a **small set of internal routes in `web.php`** — not an API layer:

- Place them under a `/pos/sync/*` prefix in `routes/web.php`
- Protect them with the **same session auth middleware** as the rest of the app, plus CSRF
- Return `response()->json(...)` directly from regular controllers — no API Resource classes
- Keep the count minimal: catalog delta, sales upload, customer balances, PLU export. Four or five routes total.

Everything else in the system — including every POS screen the cashier looks at — is server-rendered Blade. If you find yourself building a JSON endpoint for something a Blade page could render, you are doing it wrong.

### Consequence for POS authentication
Since there are no device tokens, the POS terminal authenticates by having an **operator log in via a normal Laravel session** on the store PC. Use a long session lifetime so the cashier isn't logged out mid-shift, and identify the terminal via a value stored in the session or a signed cookie set during terminal setup.

---

## Part 1 — Data Model Extensions

Extend the existing `products` table:

```php
$table->boolean('is_weighted')->default(false);      // sold by weight vs. by unit
$table->string('plu_code')->nullable()->unique();    // scale PLU code
$table->decimal('price_per_kg', 10, 2)->nullable();  // price per kilogram
$table->string('barcode')->nullable()->index();      // for non-weighted items
$table->boolean('sync_to_scale')->default(false);    // include in PLU export
```

**Note:** the `stock` column added during the e-commerce phase must be **migrated away from** — see Part 4. Write a migration that converts each product's existing stock value into an opening `adjustment` movement against the default warehouse, then drops the column.

New tables:

- **`pos_terminals`** — `id`, `name`, `code` (short prefix used in receipt numbering, e.g. `T1`), `is_active`, `last_synced_at`
- **`pos_sales`** — `id`, `uuid` (client-generated, **unique**), `terminal_id`, `receipt_number`, `subtotal`, `discount`, `total`, `payment_method`, `cash_received`, `change_given`, `sold_at` (the real time of sale on the terminal, NOT the sync time), `synced_at`, `shift_id`
- **`pos_sale_items`** — `id`, `sale_id`, `product_id`, `product_name_snapshot`, `unit_price_snapshot`, `quantity`, `weight_kg` (nullable, for weighted items), `line_total`
- **`cash_shifts`** — `id`, `terminal_id`, `opened_by`, `opened_at`, `opening_float`, `closed_at`, `counted_cash`, `expected_cash`, `variance`, `status`

**Snapshot the product name and unit price on every sale item.** Prices change; historical receipts must never change with them.

---

## Part 2 — Barcode Parser (build this as an isolated, configurable service)

Create `app/Services/WeightBarcodeParser.php` with a mirrored JavaScript implementation for the offline client. Both must read from the same config values.

```php
// config/pos.php
'barcode' => [
    'prefix_length' => 0,   // leading flag digits, if any
    'plu_length'    => 4,
    'value_length'  => 5,
    'value_type'    => 'weight_grams',  // or 'price_cents'
    'has_check_digit' => false,
],
```

Requirements:
- Input `"103100100"` → output `['plu' => '1031', 'weight_grams' => 100]`
- Must gracefully handle a plain (non-weighted) product barcode — detect it and look up by `barcode` instead of `plu_code`.
- Must return a clear, catchable error for unrecognised barcodes so the POS can show "منتج غير معروف" instead of crashing.
- Write unit tests covering: valid weighted barcode, valid unit barcode, unknown PLU, malformed input, and a boundary weight (e.g. 15.000 kg).

---

## Part 3 — Offline-First POS Client

Build the POS as a **PWA** that functions with zero connectivity.

### Local storage
- Use **IndexedDB via Dexie.js** (not raw IndexedDB, not localStorage).
- Local stores: `products` (full catalog cache), `pending_sales` (the outbox), `settings`, `sync_meta`.
- Register a **service worker** caching the app shell so the POS opens with no network at all.

### Must work fully offline
- Scan barcode → parse → look up product in local IndexedDB → compute line total
- Manual product search by name/PLU (Arabic search must work)
- Cart: add, remove, change quantity, line discount, whole-sale discount
- Complete a cash sale, calculate change
- Print receipt (see Part 5)
- Open/close a cash shift with reconciliation

### Sync engine — Outbox pattern
1. Every sale gets a **client-generated UUID (v4)** at the moment of sale, stored in `pending_sales`.
2. A background sync loop (on reconnect + periodic retry with exponential backoff) POSTs pending sales to the server.
3. Only after a confirmed `2xx` is the local record marked synced. **Never delete a local sale before server confirmation.**
4. Retain synced sales locally for a rolling window (e.g. 30 days) for offline receipt reprints.

### Server-side idempotency — CRITICAL
The `POST /pos/sync/sales` route (in `web.php`) must be **idempotent on `uuid`**:
- If the UUID already exists, return `200` with the existing sale — do **not** create a duplicate and do **not** return an error.
- Wrap sale creation in a database transaction.
- Without this, network timeouts will produce duplicate sales and corrupted revenue figures. This is the single most important correctness requirement in this brief.

### Catalog sync (server → client)
- `GET /pos/sync/catalog?since={iso_timestamp}` returns only products changed since the cursor (delta sync), plus a list of deleted product IDs.
- Client stores the new cursor in `sync_meta` and applies changes to IndexedDB.
- Provide a manual "مزامنة الآن" button plus a visible sync status indicator (online/offline, N sales pending).

### Receipt numbering
Format: `{terminal_code}-{zero-padded local sequence}`, e.g. `T1-000145`. The sequence increments locally on the terminal so two offline terminals can never collide. The server stores it as-is and does not regenerate it.

---

## Part 4 — Inventory: Multi-Warehouse Movement Ledger

**Replace the single `products.stock` column with a movement ledger.** The business has multiple stock locations (store floor, back warehouse) and receives goods from suppliers, so a single stock integer cannot represent reality.

### Core principle
Never store a mutable balance as the source of truth. Store **immutable, append-only movements**; derive balances from them. This is what makes negative stock self-correcting, makes offline sync collision-free, and gives a full audit trail per kilogram.

### Tables

**`warehouses`** — `id`, `name`, `code`, `type` (`store` | `warehouse`), `is_active`

**`stock_movements`** (append-only — never UPDATE or DELETE rows here)
- `id`, `product_id`, `warehouse_id`
- `type` — see movement types below
- `quantity` — **`decimal(12,3)`, signed** (positive = in, negative = out). Decimal is mandatory: products are sold by weight in grams.
- `unit_cost` — nullable, populated on purchase receipts for cost tracking
- `reference_type` / `reference_id` — polymorphic link to the source document (sale, purchase, transfer, adjustment)
- `notes`, `created_by`, `occurred_at`, `created_at`

**`stock_levels`** — a **cached** materialised balance per `product_id` + `warehouse_id`, for fast lookups. It is derived data: provide an artisan command to rebuild it from scratch by replaying the ledger, and make correctness of the ledger the priority over the cache.

**`suppliers`** — `id`, `name`, `phone`, `notes`

**`purchases`** (goods receipt) — `id`, `supplier_id`, `warehouse_id`, `reference_number`, `received_at`, `total_cost`, `notes`, `created_by`
**`purchase_items`** — `id`, `purchase_id`, `product_id`, `quantity` (decimal), `unit_cost`, `line_total`

**`stock_transfers`** — `id`, `from_warehouse_id`, `to_warehouse_id`, `status`, `transferred_at`, `created_by`
**`stock_transfer_items`** — `id`, `transfer_id`, `product_id`, `quantity` (decimal)

### Movement types

| Type | Sign | Source |
|---|---|---|
| `purchase_receipt` | + | Goods received from supplier |
| `pos_sale` | − | POS sale synced from a terminal |
| `online_sale` | − | E-commerce order |
| `transfer_out` | − | Leaving a warehouse |
| `transfer_in` | + | Arriving at a warehouse |
| `customer_return` | + | Returned goods |
| `supplier_return` | − | Sent back to supplier |
| `adjustment` | ± | Physical stock count correction |
| `shrinkage` | − | Natural weight loss (see below) |

### Shrinkage — important for this business
The store sells nuts, seeds and similar goods that lose weight naturally through moisture loss, roasting, and repacking. Buying a 50kg sack and selling 49.2kg is **normal shrinkage, not theft or error.** Keep `shrinkage` as its own movement type so it never pollutes the variance report used to detect real problems. Add a shrinkage report showing loss percentage per product over time.

### Negative stock is explicitly allowed
- The POS must **never** block or refuse a sale because of stock levels. The customer is at the counter holding the item.
- Balances may go negative. This is acceptable and expected. It self-corrects when the next `purchase_receipt` or `transfer_in` movement lands.
- Do **not** implement a safety buffer or reservation system. Do not add validation preventing negative balances.
- The storefront may show a low-stock indicator but should not hard-block orders unless I explicitly ask for it.

### Which warehouse does each sale draw from?
- Each `pos_terminal` is assigned a `warehouse_id`; its sales create movements against that warehouse.
- The e-commerce storefront is assigned a default fulfilment `warehouse_id`, configurable in admin.

### Offline sync implications
Because movements are append-only and each carries the sale's client-generated UUID as its reference, two offline terminals can never conflict. A replayed sync attempt must not create a second movement — enforce this via the same idempotency check used for the sale itself.

### Admin views required
- Current stock levels per product per warehouse, with a combined total
- Full movement history for any product (filterable by warehouse, type, date)
- Goods receipt entry (create a purchase → generates `purchase_receipt` movements)
- Stock transfer between warehouses (generates paired `transfer_out` / `transfer_in`)
- Physical stock count screen: enter counted quantity → system generates the `adjustment` movement for the difference
- Shrinkage report and negative-balance report

---

## Part 4b — Financial Accounts: Parties, Ledger, Treasury & Expenses

The system must handle supplier and customer accounts with running balances (Arabic accounting convention: مدين / دائن), operating expenses, and a cash treasury — comparable in scope to Egyptian retail accounting software such as "سوق المصري".

### Core principle (same as inventory)
Balances are **never stored as the source of truth.** Store append-only ledger entries; derive balances. This keeps statements auditable and makes concurrent/offline writes safe.

**Do NOT build a full double-entry general ledger with a chart of accounts.** That is overkill for this business and a common source of bugs when built by non-accountants. Build a **party ledger + treasury ledger**, which covers everything required here at a fraction of the risk.

### Tables

**`parties`** — unified model for both suppliers and customers (both carry balances; separate tables would duplicate all ledger logic)
- `id`, `type` (`supplier` | `customer`), `name`, `phone`, `notes`, `opening_balance`, `is_active`
- Customers are lightweight: **name and phone only** — no address, no login, no account portal.
- Phone should be indexed for fast POS lookup.

**`ledger_entries`** (append-only)
- `id`, `party_id`, `entry_date`
- `description` (Arabic, e.g. "فاتورة شراء #12")
- `debit` — `decimal(12,2)`, default 0
- `credit` — `decimal(12,2)`, default 0
- Exactly one of `debit`/`credit` is non-zero per row. **Do not use a single signed column** — the two-column form maps directly onto the Arabic statement UI without conversion.
- `reference_type` / `reference_id` — polymorphic link to purchase, payment, sale, return, or opening balance
- `cash_account_id` — nullable, set when the entry involves actual money movement
- `created_by`, `created_at`

**Convention (document this in code comments and enforce consistently):**
- مدين / debit = the party owes us less / we paid them
- دائن / credit = we owe them more
- Supplier balance owed by us = `SUM(credit) − SUM(debit)`
- Customer balance owed to us = `SUM(debit) − SUM(credit)`

**`cash_accounts`** (treasury) — `id`, `name` (e.g. "خزينة المحل", "خزينة الفرع"), `type` (`cash` | `bank`), `opening_balance`, `is_active`

**`cash_movements`** (append-only) — `id`, `cash_account_id`, `direction` (`in` | `out`), `amount`, `reference_type`/`reference_id`, `description`, `occurred_at`, `created_by`

Every movement of money creates a `cash_movements` row: POS cash sales in, supplier payments out, expenses out, customer receipts in.

**`payments`** — `id`, `party_id`, `cash_account_id`, `direction` (`paid` | `received`), `amount`, `payment_date`, `method`, `notes`, `created_by`
Creating a payment generates both a `ledger_entries` row and a `cash_movements` row, inside one transaction.

**`expense_categories`** — `id`, `name` (e.g. إيجار، كهرباء، مرتبات، صيانة، نقل), `is_active`

**`expenses`** — `id`, `expense_category_id`, `cash_account_id`, `warehouse_id` (nullable — to attribute costs to a branch), `amount`, `expense_date`, `description`, `attachment_path` (nullable receipt photo), `created_by`
Creating an expense generates a `cash_movements` row.

### POS integration
- Add `credit` (آجل) as a POS payment method, requiring a linked `party` of type `customer`.
- POS should support: cash, credit (آجل), and partial payment (part cash, part on account).
- A credit sale generates a `ledger_entries` debit against the customer.
- **Offline credit sales:** cache each customer's last-known balance locally along with its `last_synced_at`. Display it to the cashier with the sync timestamp clearly visible ("آخر تحديث: ..."). **Never block the sale** because the balance is stale — show the warning and let the operator decide.
- Cash sales create a `cash_movements` row against the terminal's assigned cash account on sync.

### Purchases link to the ledger
When a `purchase` (goods receipt) is recorded:
- Stock movements are created (Part 4)
- A `ledger_entries` **credit** is created against the supplier for the invoice total
- If paid immediately, a `payment` is also created (debit + cash out)

### Inventory valuation for profit reporting
Use **weighted average cost**, not FIFO. This business sells loose bulk goods (nuts, seeds) that are physically mixed in the same container — FIFO would be an accounting fiction that doesn't match reality and complicates the code for no benefit.

- Maintain a running `average_cost` per product, recalculated on each `purchase_receipt`.
- Snapshot `cost_at_sale` on every `pos_sale_items` row so historical profit reports never shift when costs change later.

---

## Part 4c — Reports

All reports must support a date range filter and, where relevant, warehouse/terminal filters. All must be printable and exportable (CSV at minimum).

**Party statements (كشف حساب)**
- Per supplier or customer: chronological entries with debit, credit, and a **running balance** column — matching the layout shown in the ledger example.
- Opening balance for the period, closing balance, and totals row.
- Summary list of all parties with current balances, sortable, showing who owes what.

**Sales reports**
- Daily / range sales totals, by terminal, by payment method (cash vs credit)
- Sales by product and by category (quantity + revenue), with weighted items shown in kg
- Best/worst sellers
- Shift-level report reconciling expected vs counted cash

**Expense reports**
- By category, by period, by warehouse/branch
- Comparison of operating expenses against revenue for the same period

**Treasury report (حركة الخزينة)**
- Opening balance, all in/out movements, closing balance, per cash account
- Must reconcile: opening + in − out = closing. Flag any discrepancy loudly.

**Profit report**
- Revenue − cost of goods sold (using snapshotted `cost_at_sale`) − operating expenses
- By period, and by product where useful
- Label it clearly as an operational estimate, not an audited financial statement.

**Inventory reports** (from Part 4)
- Stock on hand per warehouse, movement history, shrinkage, negative balances

---

## Part 5 — Bridge Agent (local, runs on the store PC)

A small standalone application (Node.js or Python — recommend whichever gives the cleanest serial/TCP support; justify the choice).

Responsibilities:
1. **PLU push to scale** — pull the PLU list from `GET /pos/sync/plu-export` (products where `sync_to_scale = true`, returning `plu_code`, Arabic name, `price_per_kg`, unit) and transmit to the ACLAS LS6 over Ethernet/TCP or RS-232.
   - Since the agent is headless and can't hold a browser session, protect this single route with a **shared secret from `.env`** checked by a small middleware — not Sanctum, not a token table. This is the only route that isn't session-authenticated.
2. **Receipt printing** — expose a tiny local HTTP endpoint (e.g. `http://localhost:9100/print`) the PWA calls to print ESC/POS receipts. Falls back to the browser's print dialog if the agent isn't running.
3. Log all operations to a local file for troubleshooting.

### Phased approach for the scale — do this
**Phase 1 (build first):** Laravel exports the PLU list as a file in the format ACLAS's own PC software imports (CSV/TXT — confirm the exact format from the ACLAS software's import screen). Store staff imports it manually. This is unblocked and can ship immediately.

**Phase 2:** Direct TCP/serial push from the bridge agent, once the ACLAS communication protocol/SDK is obtained from the local distributor.

Do not block Phase 1 waiting on the protocol documentation.

---

## Part 6 — Admin Additions

**POS & sales**
- POS sales list with filters (date, terminal, payment method), and detail view
- Terminal management (create terminals, assign codes and warehouses, view last sync time)
- Sales that arrived late (large gap between `sold_at` and `synced_at`) — useful for spotting connectivity problems

**Products & scale**
- PLU management: assign/edit PLU codes, `price_per_kg`, and the `sync_to_scale` flag

**Inventory** (Part 4)
- Stock levels, movement history, goods receipt, transfers, physical count, shrinkage and negative-balance reports

**Accounts** (Part 4b)
- Party management (suppliers and customers), with statements and current balances
- Record payments to suppliers and receipts from customers
- Expense entry with categories and optional receipt photo
- Cash account management and treasury movements

**Reports** (Part 4c) — all of the above, printable and CSV-exportable

---

## Security Requirements

- All routes — including the sync routes — sit behind standard **session authentication and CSRF protection**. No token-based auth anywhere.
- Rate-limit the sync routes via the `throttle` middleware.
- The server recalculates every line total from `unit_price_snapshot × quantity/weight` and validates it against the client-submitted total. Log any mismatch as a suspicious event rather than silently accepting the client's number.
- POS operators authenticate locally (PIN is acceptable and works offline); record `sold_by` on every sale.
- Set session cookies as `HttpOnly` and `Secure`. Ensure the sync fetch calls send the CSRF token from the meta tag.
- Keep the bridge agent's shared secret in `.env` only — never expose it to any Blade view or JavaScript.

---

## Hostinger Constraints

- Keep `QUEUE_CONNECTION=sync`, database/file sessions and cache (no Redis) — as with the existing project.
- HTTPS is mandatory: service workers and PWA installation will not function without it.
- Sync routes must be efficient — delta-only responses, paginated, gzip-friendly. Assume slow mobile connections.
- If sync volume outgrows shared hosting, note it and recommend a migration path rather than silently degrading.

---

## Deliverables

1. Migrations, models, and admin UI for all POS, inventory, and accounting entities.
2. `WeightBarcodeParser` (PHP + JS) with passing unit tests.
3. Offline-first POS PWA with service worker, Dexie.js storage, and the outbox sync engine.
4. Idempotent sync routes with tests proving duplicate UUIDs don't create duplicate sales, stock movements, or ledger entries.
5. Bridge agent (Phase 1 file export at minimum; Phase 2 stubbed with clear TODOs).
6. Documentation covering: the barcode format config, the debit/credit convention, how to set up a new terminal, and how to run the bridge agent on the store PC.
7. **A demonstrated end-to-end offline test:** disconnect the network → complete 3 sales (one cash, one credit, one partial) → reconnect → confirm exactly 3 sales appear server-side with correct totals, correct stock movements, correct ledger entries, and that re-triggering sync creates no duplicates.
8. **Financial correctness tests:**
   - A supplier's statement balance equals the sum of its ledger entries
   - Treasury reconciles: opening + in − out = closing, for every cash account
   - Rebuilding `stock_levels` from the movement ledger reproduces the cached values exactly

---

## Suggested Build Order

This is a large system. Build and verify in this order — each phase depends on the one before it:

1. **Foundations** — warehouses, stock movement ledger, parties, ledger entries, cash accounts. Migrate existing product stock into opening movements.
2. **Admin CRUD** — products/PLU, parties, expenses, goods receipt, transfers.
3. **POS online-only** — barcode parsing, cart, cash sale, receipt. Prove the money and stock math is correct before adding offline complexity.
4. **Offline layer** — service worker, Dexie cache, outbox, idempotent sync. Test aggressively.
5. **Credit sales & accounts in POS** — customer lookup, credit payment method, stale balance warnings.
6. **Reports.**
7. **Scale bridge** — Phase 1 file export, then Phase 2 direct push.

Do not build the offline layer before step 3 is proven correct. Debugging financial logic through a sync layer is significantly harder than debugging it directly.

---

## Questions to Raise Before Building

Flag these to me rather than assuming:

1. **Egyptian e-invoicing (ETA):** does this store need to issue electronic tax receipts? That requires connectivity at the moment of sale and would fundamentally change the offline design. Confirm before building.
2. Does the ACLAS LS6 documented communication protocol exist / is it obtainable from the distributor?
3. How many POS terminals will run simultaneously?
4. Should returns/refunds be supported in this phase?
5. How many warehouses/stock locations exist, and what are they?
6. Are goods bought in different units than they're sold in (e.g. purchased by the sack, sold by the kilo)? If so, a unit-conversion factor is needed on `purchase_items`.
7. Is supplier cost tracking / profit reporting needed, or is `unit_cost` just for reference?
8. Are there multiple store branches, or one location?

---

## Constraints

- Stay within the existing Laravel project — shared product catalog, shared admin.
- **Blade only.** No React, no Vue, no Inertia, no `routes/api.php`, no Sanctum. Blade + Alpine.js for every screen, with Dexie.js handling local offline storage. See the Architecture Constraint section — this is not negotiable and applies to the POS as much as the storefront.
- Don't build Phase 2 scale integration before Phase 1 works.
- Preserve full Arabic/RTL support throughout the POS interface.
