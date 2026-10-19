/* ============================================================================
   NABTA POS — till
   ----------------------------------------------------------------------------
   Cart state and money maths for the cashier screen.

   Every amount here is an INTEGER of piastres. Floats never touch money: the
   drift would surface as a drawer that is a few piastres short once a week,
   with nothing to trace it to. Pounds exist only in money() at render time and
   at the boundary where the server wants a decimal.

   This is deliberately the same shape the offline layer will need, so adding
   Dexie later is a storage change rather than a rewrite of the maths.
   ========================================================================== */
(function (w) {
  'use strict';

  var CFG = w.POS || {};

  function piastres(pounds) {
    return Math.round((parseFloat(pounds) || 0) * 100);
  }

  function pounds(p) {
    return Math.round(p) / 100;
  }

  function money(p) {
    return (Math.round(p) / 100).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }) + ' ج.م';
  }

  /** Rounds to the configured step, matching the scale so the sticker and the
   *  receipt agree in front of the customer. */
  function roundToStep(p) {
    var step = parseInt(CFG.roundingStep, 10) || 1;
    if (step <= 1) return Math.round(p);
    return Math.round(p / step) * step;
  }

  function weightedLine(pricePerKgPiastres, weightGrams) {
    return roundToStep(Math.round(pricePerKgPiastres * weightGrams / 1000));
  }

  function unitLine(unitPricePiastres, quantity) {
    return roundToStep(Math.round(unitPricePiastres * quantity));
  }

  w.till = function till() {
    return {
      /* ---- state ---- */
      catalogue: CFG.catalogue || [],
      customers: CFG.customers || [],
      query: '',
      lines: [],
      error: '',
      saving: false,
      salesThisSession: 0,

      weightPrompt: null,
      manualWeight: null,

      // Document-level scanner buffer; see initScanner().
      scanBuffer: '',
      lastScanKeyAt: 0,

      paying: false,
      method: 'cash',
      partyId: 0,
      discountPounds: 0,
      cashReceivedPounds: 0,
      completed: null,

      /** Alpine calls this automatically when the component mounts. */
      init: function () {
        this.initScanner();
      },

      /* ---- derived ---- */

      get visibleProducts() {
        var q = (this.query || '').trim();
        if (!q) return this.catalogue.slice(0, 60);

        // Arabic search has to work on a partial name, which is how a cashier
        // actually finds something they cannot scan.
        var lower = q.toLowerCase();
        return this.catalogue.filter(function (p) {
          return (p.name && p.name.indexOf(q) > -1)
            || (p.sku && p.sku.toLowerCase().indexOf(lower) > -1)
            || (p.plu && String(p.plu).indexOf(q) > -1);
        }).slice(0, 60);
      },

      get totals() {
        var subtotal = 0;
        for (var i = 0; i < this.lines.length; i++) {
          subtotal += this.lines[i].line_total_piastres;
        }
        var discount = Math.min(piastres(this.discountPounds), subtotal);
        return { subtotal: subtotal, sale_discount: discount, total: subtotal - discount };
      },

      get selectedCustomer() {
        var id = this.partyId;
        return this.customers.filter(function (c) { return c.id === id; })[0] || null;
      },

      get changeDue() {
        return Math.max(0, piastres(this.cashReceivedPounds) - this.totals.total);
      },

      /** In a partial payment, whatever cash does not cover goes on account. */
      get creditPortion() {
        return Math.max(0, this.totals.total - piastres(this.cashReceivedPounds));
      },

      get canConfirm() {
        if (this.lines.length === 0) return false;
        if (this.method === 'cash') return piastres(this.cashReceivedPounds) >= this.totals.total;
        if (this.method === 'credit') return this.partyId > 0;
        // Partial: needs a customer, and the cash must not already cover it all.
        return this.partyId > 0 && this.creditPortion > 0;
      },

      /* ---- scanning ---- */

      submitScan: function () {
        var raw = (this.query || '').trim();
        if (!raw) return;

        this.error = '';
        var parsed = w.NabtaBarcode.parse(raw);

        if (parsed.kind === 'weighted') {
          return this.addWeighted(parsed.plu, parsed.weightGrams);
        }

        if (parsed.kind === 'priced') {
          return this.addPricedByWeight(parsed.plu, parsed.pricePiastres);
        }

        if (parsed.kind === 'unit') {
          var byBarcode = this.catalogue.filter(function (p) { return p.barcode === parsed.barcode; })[0];
          if (byBarcode) return this.addProduct(byBarcode);
        }

        // Not a barcode we recognise — fall back to treating it as a search,
        // and only complain if that finds nothing either.
        var matches = this.visibleProducts;
        if (matches.length === 1) return this.addProduct(matches[0]);
        if (matches.length === 0) this.fail('منتج غير معروف');
      },

      findByPlu: function (plu) {
        return this.catalogue.filter(function (p) {
          return p.plu && String(p.plu) === String(plu);
        })[0] || null;
      },

      addWeighted: function (plu, grams) {
        var product = this.findByPlu(plu);
        if (!product) return this.fail('لا يوجد منتج بكود PLU ' + plu);

        this.pushLine({
          product_id: product.id,
          name: product.name,
          unit_price_piastres: product.unit_price_piastres,
          weight_grams: grams,
          quantity: 1,
          line_total_piastres: weightedLine(product.unit_price_piastres, grams)
        });
      },

      /** Some scales embed the price instead of the weight; honour it. */
      addPricedByWeight: function (plu, pricePiastres) {
        var product = this.findByPlu(plu);
        if (!product) return this.fail('لا يوجد منتج بكود PLU ' + plu);

        var grams = product.unit_price_piastres > 0
          ? Math.round(pricePiastres * 1000 / product.unit_price_piastres)
          : 0;

        this.pushLine({
          product_id: product.id,
          name: product.name,
          unit_price_piastres: product.unit_price_piastres,
          weight_grams: grams,
          quantity: 1,
          line_total_piastres: roundToStep(pricePiastres)
        });
      },

      addProduct: function (product) {
        // A weighted product picked by hand has no weight yet — ask for it
        // rather than guessing 1 kg.
        if (product.is_weighted) {
          this.weightPrompt = product;
          this.manualWeight = null;
          return;
        }

        // Unit lines merge; weighted lines never do, because two labels for the
        // same product carry two different weights.
        var existing = null;
        for (var i = 0; i < this.lines.length; i++) {
          if (this.lines[i].product_id === product.id && !this.lines[i].weight_grams) {
            existing = i;
            break;
          }
        }

        if (existing !== null) {
          this.changeQty(existing, 1);
        } else {
          this.pushLine({
            product_id: product.id,
            name: product.name,
            unit_price_piastres: product.unit_price_piastres,
            weight_grams: null,
            quantity: 1,
            line_total_piastres: unitLine(product.unit_price_piastres, 1)
          });
        }

        this.clearQuery();
      },

      confirmWeight: function () {
        var grams = parseInt(this.manualWeight, 10);
        if (!grams || grams <= 0) return;

        var product = this.weightPrompt;
        this.weightPrompt = null;
        this.manualWeight = null;

        this.pushLine({
          product_id: product.id,
          name: product.name,
          unit_price_piastres: product.unit_price_piastres,
          weight_grams: grams,
          quantity: 1,
          line_total_piastres: weightedLine(product.unit_price_piastres, grams)
        });
      },

      pushLine: function (line) {
        line.key = 'l' + Date.now() + Math.random().toString(36).slice(2, 7);
        this.lines.push(line);
        this.clearQuery();
      },

      changeQty: function (index, delta) {
        var line = this.lines[index];
        if (!line || line.weight_grams) return;

        var next = line.quantity + delta;
        if (next < 1) return this.removeLine(index);

        line.quantity = next;
        line.line_total_piastres = unitLine(line.unit_price_piastres, next);
      },

      removeLine: function (index) {
        this.lines.splice(index, 1);
        this.focusScan();
      },

      lineDetail: function (line) {
        if (line.weight_grams) {
          return (line.weight_grams / 1000).toFixed(3) + ' كجم × ' + money(line.unit_price_piastres) + ' / كجم';
        }
        return money(line.unit_price_piastres) + ' × ' + line.quantity;
      },

      /* ---- payment ---- */

      openPayment: function () {
        if (this.lines.length === 0) return;
        this.error = '';
        this.paying = true;
        this.method = 'cash';
        this.cashReceivedPounds = pounds(this.totals.total);
      },

      setMethod: function (method) {
        this.method = method;
        this.error = '';
        if (method === 'cash') {
          this.cashReceivedPounds = pounds(this.totals.total);
        } else if (method === 'credit') {
          this.cashReceivedPounds = 0;
        }
      },

      confirmSale: function () {
        if (!this.canConfirm || this.saving) return;

        var self = this;
        this.saving = true;
        this.error = '';

        var total = this.totals.total;
        var cash = this.method === 'credit' ? 0 : Math.min(piastres(this.cashReceivedPounds), total);
        var credit = this.method === 'cash' ? 0 : total - cash;

        var payload = {
          // Generated here so this request is safe to retry, and so the offline
          // outbox can reuse the same idempotency key unchanged.
          uuid: (w.crypto && w.crypto.randomUUID)
            ? w.crypto.randomUUID()
            : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
              }),
          payment_method: this.method,
          party_id: this.partyId || null,
          discount: pounds(this.totals.sale_discount),
          paid_cash: pounds(cash),
          paid_on_credit: pounds(credit),
          cash_received: pounds(piastres(this.cashReceivedPounds)),
          claimed_total: pounds(total),
          items: this.lines.map(function (l) {
            return {
              product_id: l.product_id,
              quantity: l.quantity,
              weight_kg: l.weight_grams ? l.weight_grams / 1000 : null
            };
          })
        };

        fetch(CFG.saleUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CFG.csrf,
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        }).then(function (res) {
          return res.json().then(function (body) {
            if (!res.ok) throw new Error(body.message || 'تعذّر حفظ الفاتورة');
            return body;
          });
        }).then(function (sale) {
          self.saving = false;
          self.paying = false;
          self.completed = sale;
          self.salesThisSession++;
        }).catch(function (e) {
          self.saving = false;
          self.error = e.message || 'تعذّر حفظ الفاتورة';
        });
      },

      printReceipt: function () {
        if (this.completed) w.open(this.completed.receipt_url, '_blank');
      },

      startNextSale: function () {
        this.completed = null;
        this.lines = [];
        this.discountPounds = 0;
        this.cashReceivedPounds = 0;
        this.partyId = 0;
        this.method = 'cash';
        this.error = '';
        this.focusScan();
      },

      /* ---- misc ---- */

      fail: function (message) {
        this.error = message;
        this.clearQuery();
      },

      clearQuery: function () {
        this.query = '';
        this.focusScan();
      },

      /* ---- scanner capture ----
         An HID barcode scanner types into whatever currently has focus and then
         presses Enter. Relying on an input box alone loses a scan every time the
         cashier has clicked a tile, a button, or anywhere on the page — which in
         a real shop is most of the time.

         So keystrokes are captured at the DOCUMENT level and routed to the scan
         handler regardless of where focus sits. Typing into a real field (the
         cash amount, the manual weight, the search box) is left alone. */

      initScanner: function () {
        var self = this;

        this.focusScan();

        document.addEventListener('keydown', function (e) {
          var el = document.activeElement;
          var tag = el ? el.tagName : '';
          var editable = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
            || (el && el.isContentEditable);

          // Inside a field that is not the scan box (cash received, manual
          // weight): the operator is typing, so keep out of the way.
          if (editable && el !== self.$refs.scan) return;

          // The scan box handles its own keys through x-model and @keydown.enter.
          if (el === self.$refs.scan) return;

          if (e.key === 'Enter') {
            if (self.scanBuffer.length > 0) {
              e.preventDefault();
              self.query = self.scanBuffer;
              self.scanBuffer = '';
              self.submitScan();
            }
            return;
          }

          // Scanners emit digits; a stray letter is a person leaning on the
          // keyboard, not a barcode.
          if (e.key.length === 1 && e.key >= '0' && e.key <= '9') {
            var now = Date.now();

            // A gap means a new scan started; scanners fire far faster than a
            // human types, so a slow "digit" never joins an existing buffer.
            if (now - self.lastScanKeyAt > 300) self.scanBuffer = '';

            self.lastScanKeyAt = now;
            self.scanBuffer += e.key;
          }
        });

        // Clicking a tile or a button steals focus; take it straight back so
        // manual typing still lands in the right place.
        document.addEventListener('click', function () {
          if (!self.paying && !self.weightPrompt && !self.completed) self.focusScan();
        });
      },

      /** The scanner types wherever focus is, so it is pulled back constantly. */
      focusScan: function () {
        var self = this;
        this.$nextTick(function () {
          if (self.$refs.scan) self.$refs.scan.focus();
        });
      },

      closeAll: function () {
        this.weightPrompt = null;
        this.paying = false;
        this.error = '';
        this.focusScan();
      },

      money: money
    };
  };
})(window);
