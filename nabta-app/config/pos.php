<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Weight barcode format
    |--------------------------------------------------------------------------
    |
    | The ACLAS LS6 prints a label whose barcode encodes a PLU and a weight.
    | The sample taken from the shop was:
    |
    |     103100100  ->  PLU 1031, 00100 = 100 g
    |
    | WARNING: that is inferred from ONE label. Before go-live, confirm the
    | format from the scale's own settings menu and correct these numbers. The
    | parser is driven entirely by this config precisely so that is a one-line
    | change rather than a code change.
    |
    | prefix_length   leading flag digits to discard (EAN-13 in-store prefixes
    |                 such as "2" live here)
    | plu_length      digits of PLU
    | value_length    digits of the embedded value
    | value_type      weight_grams | price_piastres
    | has_check_digit trailing check digit to discard
    |
    */

    'barcode' => [
        'prefix_length' => (int) env('POS_BARCODE_PREFIX_LENGTH', 0),
        'plu_length' => (int) env('POS_BARCODE_PLU_LENGTH', 4),
        'value_length' => (int) env('POS_BARCODE_VALUE_LENGTH', 5),
        'value_type' => env('POS_BARCODE_VALUE_TYPE', 'weight_grams'),
        'has_check_digit' => (bool) env('POS_BARCODE_HAS_CHECK_DIGIT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rounding
    |--------------------------------------------------------------------------
    |
    | price_per_kg x weight rarely lands on a whole piastre. The scale prints its
    | own price on the label, so the POS must round the same way the scale does
    | or the receipt will disagree with the sticker in the customer's hand.
    |
    | Confirm the scale's rounding before go-live. `step` is in piastres:
    | 1 = nearest piastre, 5 = nearest 5 piastres, 25 = nearest quarter pound.
    |
    */

    'rounding' => [
        'step_piastres' => (int) env('POS_ROUNDING_STEP', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | PLU export
    |--------------------------------------------------------------------------
    |
    | Phase 1 of the scale integration: admin downloads a file, staff imports it
    | with the ACLAS PC software. Confirm the column order against that import
    | screen. Phase 2 (direct TCP/serial push) needs the vendor protocol.
    |
    */

    'plu_export' => [
        'delimiter' => env('POS_PLU_DELIMITER', ','),
        'encoding' => env('POS_PLU_ENCODING', 'UTF-8'),
    ],

];
