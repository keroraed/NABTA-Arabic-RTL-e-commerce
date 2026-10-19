/* ============================================================================
   Weight barcode parser — the JS mirror of App\Services\Pos\WeightBarcodeParser.
   ----------------------------------------------------------------------------
   Both read the SAME config, injected into the page from Blade, so there is one
   source of truth for the format. If you change one of these, change the other
   and the unit tests for both.

   The format was inferred from a single label sample and must be confirmed
   against the scale's own settings menu before go-live — which is why nothing
   here is hardcoded.
   ========================================================================== */
(function (w) {
  'use strict';

  function config() {
    var c = (w.POS && w.POS.barcode) || {};
    return {
      prefixLength: parseInt(c.prefix_length, 10) || 0,
      pluLength: parseInt(c.plu_length, 10) || 4,
      valueLength: parseInt(c.value_length, 10) || 5,
      valueType: c.value_type || 'weight_grams',
      hasCheckDigit: !!c.has_check_digit
    };
  }

  function weightedLength(c) {
    return c.prefixLength + c.pluLength + c.valueLength + (c.hasCheckDigit ? 1 : 0);
  }

  /**
   * Returns one of:
   *   { kind: 'weighted', plu, weightGrams }
   *   { kind: 'priced',   plu, pricePiastres }
   *   { kind: 'unit',     barcode }
   *   { kind: 'invalid',  reason }
   *
   * A barcode whose length does not match the weighted layout is a plain unit
   * barcode, not an error, so an ordinary retail EAN scans straight through.
   */
  function parse(raw) {
    var c = config();
    var s = String(raw == null ? '' : raw).trim();

    if (!s || !/^\d+$/.test(s)) {
      return { kind: 'invalid', reason: 'باركود غير معروف' };
    }

    if (s.length !== weightedLength(c)) {
      return { kind: 'unit', barcode: s };
    }

    var offset = c.prefixLength;
    var plu = s.substr(offset, c.pluLength);
    var value = parseInt(s.substr(offset + c.pluLength, c.valueLength), 10);

    // A zero-value weighted label would ring up as free.
    if (!value || value <= 0) {
      return { kind: 'invalid', reason: 'الباركود يحتوي على وزن صفر' };
    }

    return c.valueType === 'price_piastres'
      ? { kind: 'priced', plu: plu, pricePiastres: value }
      : { kind: 'weighted', plu: plu, weightGrams: value };
  }

  w.NabtaBarcode = { parse: parse, weightedLength: function () { return weightedLength(config()); } };
})(window);
