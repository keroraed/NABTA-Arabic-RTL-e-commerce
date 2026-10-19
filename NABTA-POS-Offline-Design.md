# NABTA POS — Offline Layer Technical Design

Companion to `NABTA-POS-Scale-Offline-Prompt.md`. This document specifies **how** the offline layer is built. Follow it closely — most of these rules exist because the obvious implementation loses sales data.

Stack constraint: **Blade + Alpine.js + Dexie.js + Service Worker.** No SPA framework, no `routes/api.php`, no token auth.

---

## 1. Non-Negotiable Rules

Read these before writing any code. Every one of them corresponds to a real failure mode that loses money.

1. **A sale is written to IndexedDB before anything else happens.** Not after a network attempt, not optimistically in memory. The local write is the commit point. If the local write fails, the sale did not happen and the cashier must be told.
2. **Never delete a local sale until the server has confirmed it.** Mark it synced; delete only after a retention window.
3. **Money is stored as integer piastres. Weight is stored as integer grams.** Never use JavaScript floats for either, anywhere in the client. Convert to display units only at render time.
4. **The service worker must never cache anything under `/pos/sync/*`.** Those routes are network-only.
5. **The server route must be idempotent on the sale UUID.** A retried sale returns the existing record with `200`, never a duplicate and never an error.
6. **Request persistent storage** via `navigator.storage.persist()`. If denied, warn the operator prominently — their unsynced data is evictable.
7. **The cashier must always be able to complete a sale.** No stock check, no balance check, no network check may ever block the sale button.

---

## 2. Client Architecture

Four layers, each with one job:

```
Blade view (POS screen)
  └─ Alpine.js components — UI state, cart, keypad, scanner input
       └─ pos-core.js      — barcode parsing, pricing math, cart totals (pure functions)
       └─ pos-db.js        — Dexie wrapper: all IndexedDB reads/writes
       └─ pos-sync.js      — outbox engine, catalog sync, connectivity state
  └─ sw.js                 — service worker: app shell caching only
```

**Keep pricing math in pure functions** in `pos-core.js` with no DB or network access. This lets you unit-test the money logic — the part that must not be wrong — in isolation.

Mirror `WeightBarcodeParser` here in JS, reading the same config values as the PHP version. Inject the config into the page from Blade so there is one source of truth.

---

## 3. Dexie Schema

```js
db.version(1).stores({
  products:      'id, plu_code, barcode, name, category_id, updated_at',
  customers:     'id, phone, name, updated_at',
  pending_sales: 'uuid, status, created_at, attempts',
  synced_sales:  'uuid, receipt_number, sold_at',
  settings:      'key',
  sync_meta:     'key'
});
```

**`pending_sales.status`** — one of:
- `pending` — awaiting upload
- `sending` — upload in flight (reset to `pending` on startup, in case of a crash mid-send)
- `failed` — permanently rejected; needs human attention, no further automatic retries

**`sync_meta`** holds: `catalog_cursor`, `customers_cursor`, `last_sync_at`, `clock_offset_ms`, `receipt_sequence`.

**Money and weight fields** in every stored record are integers: `total_piastres`, `unit_price_piastres`, `weight_grams`.

---

## 4. The Sale Write Path

This is the most important code path in the system. It must be a **single Dexie transaction** covering every write:

```js
await db.transaction('rw',
  db.pending_sales, db.products, db.sync_meta,
  async () => {
    const seq = await nextReceiptSequence();      // increments sync_meta
    const sale = buildSale(cart, seq, terminalCode);
    await db.pending_sales.add(sale);             // sale + outbox in one record
    await decrementLocalStock(cart);              // cached display value only
  }
);
```

Rules:
- The receipt sequence increments **inside** the transaction. Never in `localStorage`, never outside.
- Receipt number format: `{terminal_code}-{6-digit sequence}` → `T1-000145`.
- The sale record **is** the outbox entry. Do not maintain a separate queue table that can drift out of sync with the sales table.
- `uuid` is generated with `crypto.randomUUID()` at the moment of sale.
- Local stock decrement affects the **cached display value only**. The server's movement ledger is the real record.
- If the transaction throws, show a blocking error. Do not print a receipt for a sale that wasn't stored.

### Timestamps and clock skew
Store three values on every sale:
- `sold_at_device` — the terminal's clock
- `clock_offset_ms` — cached difference between device and server clock, captured on each successful sync
- `sold_at_corrected` — computed, sent to the server as the authoritative sale time

The server additionally records its own `received_at`. Flag sales in admin where `|offset| > 5 minutes` so a badly-set store clock is visible rather than silently corrupting reports.

---

## 5. The Sync Engine

### Triggers
Run the sync loop on:
- `window.addEventListener('online')`
- `document.visibilitychange` → visible
- A timer every 60s while pending items exist
- Manual "مزامنة الآن" button

### Single-flight lock (multi-tab safety)
If the cashier has two tabs open, both will try to sync. Guard with the **Web Locks API**:

```js
await navigator.locks.request('pos-sync', { ifAvailable: true }, async lock => {
  if (!lock) return;   // another tab is syncing
  await runSync();
});
```

Server idempotency protects correctness here, but the lock avoids wasted requests and confusing UI.

### Batching
Upload in batches of **20 sales**, oldest first. Do not send hundreds in one request — shared hosting and a weak connection will time out and you'll retry the whole batch forever.

### Connectivity detection
`navigator.onLine` is unreliable — it reports "online" when connected to a router with no internet. Treat a **successful sync response as the only proof of connectivity.** Use `navigator.onLine === false` as a fast negative signal only.

### Error handling — full taxonomy

| Response | Meaning | Action |
|---|---|---|
| `200` / `201` | Accepted (or already existed — idempotent) | Move to `synced_sales`, clear from pending |
| Network error, timeout | Offline or server unreachable | Keep `pending`, exponential backoff |
| `500`–`599` | Server fault | Keep `pending`, exponential backoff, log |
| `401` / `419` | **Session expired** | **Stop the loop. Show a loud, persistent banner telling the cashier to log in again.** Never fail silently — this is how a full day of sales quietly fails to upload. |
| `422` | Validation rejected — poison message | Mark `failed`, remove from retry queue, surface in POS UI and admin |
| `429` | Rate limited | Back off, respect `Retry-After` |

### Backoff
Exponential with jitter: `min(60s × 2^attempts, 5min) × (0.5 + Math.random())`. Cap `attempts` — after ~10 failures with only transient errors, keep retrying but raise the UI warning level.

---

## 6. Catalog & Customer Sync (server → client)

- `GET /pos/sync/catalog?since={cursor}` returns changed products plus `deleted_ids`, and the server's current timestamp (used for clock offset).
- Same pattern for `GET /pos/sync/customers` — name, phone, and last-known balance.
- Apply changes in a Dexie transaction; only then advance the cursor. Never advance the cursor before the write succeeds.
- **Run a full reconcile weekly** (cursor reset) to catch drift from any missed delta.
- On first load, the full catalog download must show a progress indicator — this store may have thousands of products.

### Customer balances offline
Cache each customer's balance with its sync timestamp. In the POS, display it as:

> الرصيد: 1,500 ج — آخر تحديث: قبل ساعتين

Never block a credit sale on a stale balance. Show the staleness and let the operator judge.

---

## 7. Service Worker

Keep it minimal. A clever service worker is a liability here.

- **Precache the app shell:** POS HTML, CSS, JS, fonts, logo. Cache-first.
- **Network-only, explicitly excluded from all caching:** every route under `/pos/sync/*`.
- **Product images:** cache-first with a size cap, or skip images in the POS entirely — a text-first POS is faster and avoids quota pressure.
- **Do not use Background Sync API** as the primary mechanism. Support is inconsistent and it hides failures from the UI. Use it only as an optional supplement to the in-page loop.
- Version the cache and clean old caches on `activate`. Provide a visible "تحديث التطبيق" action so a stale shell can be forced to refresh.

---

## 8. Operator-Facing Health UI

The single biggest real-world failure is **silent** desync: everything looks fine, nothing has uploaded for three days. Make state impossible to miss.

Persistent status bar showing:
- Connection state: متصل / غير متصل
- Pending count: "3 فواتير في انتظار الرفع"
- Last successful sync time
- **Age of the oldest pending sale** — this is the number that matters

Escalation:
- Oldest pending > 2 hours → amber warning
- Oldest pending > 12 hours, or any `failed` sale exists → red persistent banner that cannot be dismissed
- Session expired → red banner with a direct login link

Also provide:
- A "تصدير الفواتير المعلقة" button producing a JSON file — the recovery path if a device dies or won't sync
- A matching admin import screen that accepts that file, running through the same idempotent path

---

## 9. Server Side (Laravel, `routes/web.php`)

```php
Route::middleware(['auth', 'throttle:120,1'])->prefix('pos/sync')->group(function () {
    Route::get('catalog',   [PosSyncController::class, 'catalog']);
    Route::get('customers', [PosSyncController::class, 'customers']);
    Route::post('sales',    [PosSyncController::class, 'storeSales']);
});
```

`storeSales` accepts a batch and returns a per-sale result array so a single bad sale doesn't fail the batch:

```json
{
  "server_time": "2026-09-04T10:22:31Z",
  "results": [
    { "uuid": "...", "status": "created",   "receipt_number": "T1-000145" },
    { "uuid": "...", "status": "duplicate", "receipt_number": "T1-000144" },
    { "uuid": "...", "status": "rejected",  "error": "منتج غير موجود" }
  ]
}
```

### Idempotency implementation
- Unique index on `pos_sales.uuid`.
- Process each sale inside a DB transaction: insert sale → insert items → insert stock movements → insert ledger entries (if credit) → insert cash movement (if cash).
- Catch the unique-constraint violation on `uuid` and return `duplicate` with the existing record. Do **not** rely on a `SELECT` check alone — two concurrent requests can both pass it.
- **Every downstream side effect must be inside the same transaction as the sale insert.** A sale that commits without its stock movement is worse than a sale that fails outright.

### Server-side recalculation
Recompute every line total from the product's stored price and the submitted quantity/weight. Compare against the client's total. On mismatch: accept the sale (the money was already taken from the customer) but **log it as a discrepancy for review**. Never silently trust, never silently reject.

---

## 10. Testing — required before this ships

Automated:
- Same batch POSTed twice → exactly one sale, one set of stock movements, one ledger entry
- Concurrent duplicate requests (parallel) → still exactly one sale
- Sale with a deleted product → `rejected`, batch continues
- `pos-core.js` money math: totals, weighted items, discounts, rounding — in integer piastres

Manual, on the actual store PC:
1. Disconnect network → complete 5 sales (cash, credit, weighted, discounted, partial payment)
2. **Force-close the browser entirely** → reopen → confirm all 5 still pending
3. Reconnect → confirm all 5 upload with correct totals, stock, and ledger effects
4. Trigger sync again → confirm no duplicates
5. Expire the session server-side while offline sales are pending → confirm the red banner appears and the cashier is not left guessing
6. Fill the disk / simulate storage pressure → confirm the persistence warning behaves correctly
7. Set the terminal clock 3 days wrong → confirm sales still sync and the skew is flagged in admin

Do not consider the offline layer done until step 2 and step 5 both pass. They are the ones that fail in real stores.
