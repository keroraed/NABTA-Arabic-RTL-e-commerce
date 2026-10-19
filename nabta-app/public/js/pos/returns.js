/* ============================================================================
   NABTA POS — returns
   ----------------------------------------------------------------------------
   Helps the cashier pick quantities and previews the refund. Both real rules —
   refund at the ORIGINAL price, and never more than is still outstanding on a
   line — are enforced on the server; this only mirrors them so the number on
   screen matches the number that will be paid out.

   Integer piastres throughout, same as the till.
   ========================================================================== */
(function (w) {
  'use strict';

  var CFG = w.POS || {};

  function money(p) {
    return (Math.round(p) / 100).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }) + ' ج.م';
  }

  function roundToStep(p) {
    var step = parseInt(CFG.roundingStep, 10) || 1;
    if (step <= 1) return Math.round(p);
    return Math.round(p / step) * step;
  }

  w.returns = function returns() {
    return {
      lines: CFG.lines || [],
      hasCustomer: !!CFG.hasCustomer,
      quantities: {},
      method: 'cash',
      reason: '',
      error: '',
      saving: false,
      completed: null,

      /** Never let the box exceed what is actually still returnable. */
      clamp: function (line) {
        var value = parseFloat(this.quantities[line.id]);

        if (isNaN(value) || value < 0) {
          this.quantities[line.id] = '';
          return;
        }

        if (value > line.returnable) {
          this.quantities[line.id] = line.returnable;
        }
      },

      get refundTotal() {
        var total = 0;

        for (var i = 0; i < this.lines.length; i++) {
          var line = this.lines[i];
          var qty = parseFloat(this.quantities[line.id]);

          if (isNaN(qty) || qty <= 0) continue;

          var gross = line.is_weighted
            ? roundToStep(Math.round(line.unit_price_piastres * Math.round(qty * 1000) / 1000))
            : roundToStep(Math.round(line.unit_price_piastres * qty));

          // Proportional share of the original line discount.
          var share = line.sold > 0 ? qty / line.sold : 0;
          var discount = Math.round(line.discount_piastres * share);

          total += Math.max(0, gross - discount);
        }

        return total;
      },

      fmtQty: function (q) {
        var s = Number(q).toFixed(3);
        return s.replace(/\.?0+$/, '');
      },

      unitSuffix: function (line) {
        return line.is_weighted ? ' كجم' : '';
      },

      unitLabel: function (line) {
        return money(line.unit_price_piastres) + (line.is_weighted ? ' / كجم' : '');
      },

      submit: function () {
        if (this.refundTotal <= 0 || this.saving) return;

        var self = this;
        this.saving = true;
        this.error = '';

        var quantities = {};
        for (var i = 0; i < this.lines.length; i++) {
          var id = this.lines[i].id;
          var qty = parseFloat(this.quantities[id]);
          if (!isNaN(qty) && qty > 0) quantities[id] = qty;
        }

        fetch(CFG.storeUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CFG.csrf,
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            // Generated here so the request is safe to retry, exactly as a sale is.
            uuid: (w.crypto && w.crypto.randomUUID)
              ? w.crypto.randomUUID()
              : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                  var r = Math.random() * 16 | 0;
                  return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
                }),
            refund_method: self.method,
            reason: self.reason || null,
            quantities: quantities
          })
        }).then(function (res) {
          return res.json().then(function (body) {
            if (!res.ok) throw new Error(body.message || 'تعذّر تسجيل المرتجع');
            return body;
          });
        }).then(function (result) {
          self.saving = false;
          self.completed = result;
        }).catch(function (e) {
          self.saving = false;
          self.error = e.message || 'تعذّر تسجيل المرتجع';
        });
      },

      printReceipt: function () {
        if (this.completed) w.open(this.completed.receipt_url, '_blank');
      },

      money: money
    };
  };
})(window);
