<?php

return [

    /* ---- chrome ---- */
    'title' => 'Point of Sale',
    'dashboard' => 'Dashboard',
    'close_shift' => 'Close shift',
    'returns' => 'Returns',
    'new_sale' => 'New sale',
    'sign_out' => 'Sign out',
    'invoices_count' => 'Sales: :count',

    /* ---- till ---- */
    'scan_placeholder' => 'Scan a barcode or search by name…',
    'sold_by_weight' => 'Sold by weight',
    'per_kg' => '/ kg',
    'cart_empty' => 'Scan an item to start',
    'remove' => 'Remove',
    'subtotal' => 'Subtotal',
    'discount' => 'Discount',
    'total' => 'Total',
    'pay' => 'Pay',
    'saving' => 'Saving…',

    /* ---- weight prompt ---- */
    'weight_for' => 'Weight — :product',
    'weight_grams' => 'Weight in grams',
    'price_per_kg_is' => 'Price per kg: :price',
    'add' => 'Add',
    'cancel' => 'Cancel',

    /* ---- payment ---- */
    'payment' => 'Payment',
    'cash' => 'Cash',
    'credit' => 'On account',
    'partial' => 'Partial',
    'sale_discount' => 'Discount on sale',
    'customer' => 'Customer',
    'choose_customer' => '— choose a customer —',
    'current_balance' => 'Currently owes: :amount',
    'cash_received' => 'Cash received',
    'cash_paid' => 'Paid in cash',
    'change_due' => 'Change due',
    'added_to_account' => 'Added to account',
    'confirm_sale' => 'Confirm sale',

    /* ---- completed ---- */
    'sale_done' => 'Sale complete',
    'print_receipt' => 'Print receipt',

    /* ---- shift ---- */
    'open_shift' => 'Open shift',
    'opening_float' => 'Opening cash in drawer',
    'start_shift' => 'Start shift',
    'change_terminal' => 'Change till',
    'shift_gate_note' => ':terminal. No sale can be recorded before a shift is open, so every sale stays reconcilable at the end of the day.',
    'shift_started' => 'started :at',
    'invoice_count' => 'Sales',
    'expected_in_drawer' => 'Expected in drawer',
    'counted_cash' => 'Cash actually counted',
    'variance' => 'Variance',
    'notes_optional' => 'Notes (optional)',
    'shift_closed' => 'Shift closed. Variance: :variance',
    'back' => 'Back',

    /* ---- terminal picker ---- */
    'choose_terminal' => 'Choose a till',
    'terminal_note' => 'The choice is saved on this machine, so it is not asked again. The till code appears in receipt numbers.',
    'no_terminals' => 'No active tills. Add one from the admin panel first.',
    'warehouse' => 'Warehouse',
    'continue' => 'Continue',

    /* ---- returns ---- */
    'return_title' => 'Return',
    'find_receipt' => 'Find by receipt number',
    'receipt_number' => 'Receipt number',
    'search' => 'Search',
    'receipt_not_found' => 'No sale found with that receipt number.',
    'original_sale' => 'Original sale',
    'sold_at' => 'Sold at',
    'sold_qty' => 'Sold',
    'already_returned' => 'Already returned',
    'returnable' => 'Available to return',
    'return_qty' => 'Quantity to return',
    'refund_total' => 'Refund total',
    'refund_method' => 'Refund method',
    'refund_cash' => 'Cash from the drawer',
    'refund_credit' => 'Credit the customer account',
    'reason' => 'Reason (optional)',
    'confirm_return' => 'Confirm return',
    'return_done' => 'Return recorded',
    'refunded_amount' => 'Amount refunded',
    'fully_returned' => 'This sale has already been returned in full.',
    'nothing_selected' => 'Choose a quantity to return first.',

    /* ---- errors ---- */
    'no_terminal' => 'No till selected',
    'no_open_shift' => 'No open shift',
    'unknown_product' => 'Unknown product',
    'no_product_with_plu' => 'No product with PLU :plu',
    'zero_weight' => 'The barcode contains a zero weight',
    'save_failed' => 'Could not save the sale',
    'credit_needs_customer' => 'An account sale needs a customer',
    'return_failed' => 'Could not record the return',
    'cannot_return_a_return' => 'A return cannot itself be returned.',
    'line_not_in_sale' => 'That line is not part of the original sale.',
    'over_return' => 'Only :available of «:product» is available to return.',
    'nothing_to_return' => 'No quantity was selected to return.',
    'credit_refund_needs_customer' => 'Refunding to account requires a customer on the sale.',

    /* ---- ledger descriptions ---- */
    'return_of' => 'Return of receipt :receipt',
    'refund_for' => 'Refund for receipt :receipt',

    /* ---- receipt ---- */
    'receipt' => 'Receipt',
    'return_receipt' => 'Return receipt',
    'invoice' => 'Receipt',
    'date' => 'Date',
    'terminal' => 'Till',
    'seller' => 'Sold by',
    'payment_method' => 'Payment method',
    'on_account' => 'On account',
    'remaining' => 'Change',
    'thanks' => 'Thank you for shopping with NABTA',
    'print' => 'Print',
    'back_to_till' => 'Back to till',
];
