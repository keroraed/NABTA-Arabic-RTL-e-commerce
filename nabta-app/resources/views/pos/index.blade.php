{{--
  The till.

  All money in this page is integer piastres — the same rule the offline design
  sets, adopted now so the maths never has to be revisited. Pounds appear only
  at render time, through money().

  The cart lives in Alpine; nothing is written until the cashier confirms
  payment, at which point one POST records the sale, its stock movements and its
  ledger effects in a single server-side transaction.
--}}
@extends('layouts.pos')
@section('title', 'نقطة البيع')

@php
    $posConfig = [
        'catalogue' => $catalogue,
        'customers' => $customers,
        'barcode' => $barcodeConfig,
        'roundingStep' => $roundingStep,
        'saleUrl' => route('pos.sales.store'),
        'csrf' => csrf_token(),
    ];
@endphp

@push('scripts')
  <script defer src="{{ asset('js/pos/barcode.js') }}?v={{ filemtime(public_path('js/pos/barcode.js')) }}"></script>
  <script defer src="{{ asset('js/pos/till.js') }}?v={{ filemtime(public_path('js/pos/till.js')) }}"></script>
@endpush

@section('body')
<div class="pos-shell" x-data="till()" @keydown.window.escape="closeAll()">

  <header class="pos-bar">
    <img src="{{ asset('assets/logo.webp') }}" alt="نبته">
    <span class="chip">{{ $terminal->code }} — {{ $terminal->name }}</span>
    <span class="chip">{{ $terminal->warehouse?->name }}</span>
    <span class="spacer"></span>
    <span class="chip" x-text="'الفواتير: ' + salesThisSession"></span>
    @can('pos.refund')
      <a href="{{ route('pos.returns') }}">{{ __('pos.returns') }}</a>
    @endcan
    <a href="{{ route('pos.shift.close') }}">{{ __('pos.close_shift') }}</a>
    <a href="{{ route('filament.admin.pages.dashboard') }}">لوحة التحكم</a>
  </header>

  <div class="pos-body">
    <main class="pos-main">
      <div class="scan-row">
        {{-- The scanner is an HID keyboard: it types the digits and presses
             Enter. Focus is pulled back here after every action so a scan is
             never swallowed by whatever the cashier last clicked. --}}
        <input class="scan-input"
               x-ref="scan"
               x-model="query"
               @keydown.enter.prevent="submitScan()"
               placeholder="امسح الباركود أو ابحث بالاسم…"
               autocomplete="off"
               autofocus>
      </div>

      <template x-if="error">
        <p class="pos-alert" x-text="error"></p>
      </template>

      <div class="product-grid">
        <template x-for="product in visibleProducts" :key="product.id">
          <button type="button" class="product-tile" @click="addProduct(product)">
            <span class="name" x-text="product.name"></span>
            <span class="meta" x-text="product.category"></span>
            <template x-if="product.is_weighted">
              <span class="weighted">يُباع بالوزن</span>
            </template>
            <span class="price" x-text="money(product.unit_price_piastres) + (product.is_weighted ? ' / كجم' : '')"></span>
          </button>
        </template>
      </div>
    </main>

    <aside class="pos-cart">
      <div class="cart-lines">
        <template x-if="lines.length === 0">
          <p class="cart-empty">امسح منتجاً للبدء</p>
        </template>

        <template x-for="(line, index) in lines" :key="line.key">
          <div class="cart-line">
            <div>
              <div class="name" x-text="line.name"></div>
              <div class="detail" x-text="lineDetail(line)"></div>
              <div class="controls">
                {{-- Weighted lines have no quantity stepper: the weight came
                     from the scale label and must not be nudged by hand. --}}
                <template x-if="!line.weight_grams">
                  <button type="button" class="qbtn" @click="changeQty(index, -1)">−</button>
                </template>
                <template x-if="!line.weight_grams">
                  <span x-text="line.quantity"></span>
                </template>
                <template x-if="!line.weight_grams">
                  <button type="button" class="qbtn" @click="changeQty(index, 1)">+</button>
                </template>
                <button type="button" class="qbtn remove" @click="removeLine(index)">حذف</button>
              </div>
            </div>
            <div class="total" x-text="money(line.line_total_piastres)"></div>
          </div>
        </template>
      </div>

      <div class="cart-foot">
        <div class="row">
          <span>الإجمالي الفرعي</span>
          <span x-text="money(totals.subtotal)"></span>
        </div>
        <template x-if="totals.sale_discount > 0">
          <div class="row">
            <span>الخصم</span>
            <span x-text="'- ' + money(totals.sale_discount)"></span>
          </div>
        </template>
        <div class="row">
          <span>الإجمالي</span>
          <span class="grand" x-text="money(totals.total)"></span>
        </div>

        <button type="button" class="btn-pay" :disabled="lines.length === 0 || saving" @click="openPayment()">
          <span x-text="saving ? 'جارٍ الحفظ…' : 'الدفع'"></span>
        </button>
      </div>
    </aside>
  </div>

  {{-- ---------------------------------------------------------------- --}}
  {{-- Manual weight entry: for when a label is smudged, missing, or the  --}}
  {{-- scale is busy. Without it the cashier is stuck mid-queue.          --}}
  <template x-if="weightPrompt">
    <div class="modal-scrim" @click.self="weightPrompt = null">
      <div class="modal">
        <h2 x-text="'وزن — ' + weightPrompt.name"></h2>
        <div class="field">
          <label for="manual-weight">الوزن بالجرام</label>
          <input id="manual-weight" type="number" min="1" step="1"
                 x-model.number="manualWeight"
                 @keydown.enter.prevent="confirmWeight()"
                 x-init="$nextTick(() => $el.focus())">
        </div>
        <p class="balance-note" x-text="'سعر الكيلو: ' + money(weightPrompt.unit_price_piastres)"></p>
        <div class="modal-actions">
          <button type="button" class="btn-confirm" :disabled="!manualWeight" @click="confirmWeight()">إضافة</button>
          <button type="button" class="btn-cancel" @click="weightPrompt = null">إلغاء</button>
        </div>
      </div>
    </div>
  </template>

  {{-- ---------------------------------------------------------------- --}}
  {{-- Payment                                                           --}}
  <template x-if="paying">
    <div class="modal-scrim" @click.self="paying = false">
      <div class="modal">
        <h2>الدفع</h2>

        <div class="pay-methods">
          <button type="button" :aria-pressed="method === 'cash'" @click="setMethod('cash')">نقدي</button>
          <button type="button" :aria-pressed="method === 'credit'" @click="setMethod('credit')">آجل</button>
          <button type="button" :aria-pressed="method === 'partial'" @click="setMethod('partial')">جزئي</button>
        </div>

        <div class="field">
          <label for="sale-discount">خصم على الفاتورة</label>
          <input id="sale-discount" type="number" min="0" step="0.25" x-model.number="discountPounds">
        </div>

        <div class="row" style="display:flex;justify-content:space-between;font-size:20px;font-weight:700;margin-bottom:18px">
          <span>الإجمالي</span>
          <span x-text="money(totals.total)"></span>
        </div>

        <template x-if="method !== 'cash'">
          <div>
            <div class="field">
              <label for="customer">العميل</label>
              <select id="customer" x-model.number="partyId">
                <option value="0">— اختر عميلاً —</option>
                <template x-for="c in customers" :key="c.id">
                  <option :value="c.id" x-text="c.name + (c.phone ? ' — ' + c.phone : '')"></option>
                </template>
              </select>
            </div>
            {{-- The balance is shown, never used to block: whether to extend
                 more credit is the operator's call, not the software's. --}}
            <template x-if="selectedCustomer">
              <p class="balance-note" x-text="'الرصيد الحالي عليه: ' + selectedCustomer.balance.toFixed(2) + ' ج.م'"></p>
            </template>
          </div>
        </template>

        <template x-if="method === 'cash' || method === 'partial'">
          <div class="field">
            <label for="cash-received" x-text="method === 'partial' ? 'المدفوع نقداً' : 'المبلغ المستلم'"></label>
            <input id="cash-received" type="number" min="0" step="0.25"
                   x-model.number="cashReceivedPounds"
                   x-init="$nextTick(() => $el.focus())"
                   @keydown.enter.prevent="canConfirm && confirmSale()">
          </div>
        </template>

        <template x-if="method === 'cash' && changeDue > 0">
          <div class="change-due">
            <div>الباقي للعميل</div>
            <div class="amount" x-text="money(changeDue)"></div>
          </div>
        </template>

        <template x-if="method === 'partial'">
          <div class="change-due">
            <div>يُضاف على الحساب</div>
            <div class="amount" x-text="money(creditPortion)"></div>
          </div>
        </template>

        <template x-if="error">
          <p class="pos-alert" x-text="error"></p>
        </template>

        <div class="modal-actions">
          <button type="button" class="btn-confirm" :disabled="!canConfirm || saving" @click="confirmSale()">
            <span x-text="saving ? 'جارٍ الحفظ…' : 'تأكيد البيع'"></span>
          </button>
          <button type="button" class="btn-cancel" @click="paying = false">إلغاء</button>
        </div>
      </div>
    </div>
  </template>

  {{-- ---------------------------------------------------------------- --}}
  {{-- Completed                                                         --}}
  <template x-if="completed">
    <div class="modal-scrim">
      <div class="modal" style="text-align:center">
        <h2>تم البيع</h2>
        <p style="font-size:20px;font-weight:700" x-text="completed.receipt_number"></p>
        <template x-if="completed.change_given > 0">
          <div class="change-due">
            <div>الباقي للعميل</div>
            <div class="amount" x-text="completed.change_given.toFixed(2) + ' ج.م'"></div>
          </div>
        </template>
        <div class="modal-actions">
          <button type="button" class="btn-confirm" @click="printReceipt()">طباعة الإيصال</button>
          <button type="button" class="btn-cancel" @click="startNextSale()">فاتورة جديدة</button>
        </div>
      </div>
    </div>
  </template>

</div>
@endsection
