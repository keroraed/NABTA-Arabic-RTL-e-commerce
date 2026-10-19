<?php

return [

    /* ---- chrome ---- */
    'title' => 'نقطة البيع',
    'dashboard' => 'لوحة التحكم',
    'close_shift' => 'إغلاق الوردية',
    'returns' => 'المرتجعات',
    'new_sale' => 'بيع جديد',
    'sign_out' => 'تسجيل الخروج',
    'invoices_count' => 'الفواتير: :count',

    /* ---- till ---- */
    'scan_placeholder' => 'امسح الباركود أو ابحث بالاسم…',
    'sold_by_weight' => 'يُباع بالوزن',
    'per_kg' => '/ كجم',
    'cart_empty' => 'امسح منتجاً للبدء',
    'remove' => 'حذف',
    'subtotal' => 'الإجمالي الفرعي',
    'discount' => 'الخصم',
    'total' => 'الإجمالي',
    'pay' => 'الدفع',
    'saving' => 'جارٍ الحفظ…',

    /* ---- weight prompt ---- */
    'weight_for' => 'وزن — :product',
    'weight_grams' => 'الوزن بالجرام',
    'price_per_kg_is' => 'سعر الكيلو: :price',
    'add' => 'إضافة',
    'cancel' => 'إلغاء',

    /* ---- payment ---- */
    'payment' => 'الدفع',
    'cash' => 'نقدي',
    'credit' => 'آجل',
    'partial' => 'جزئي',
    'sale_discount' => 'خصم على الفاتورة',
    'customer' => 'العميل',
    'choose_customer' => '— اختر عميلاً —',
    'current_balance' => 'الرصيد الحالي عليه: :amount',
    'cash_received' => 'المبلغ المستلم',
    'cash_paid' => 'المدفوع نقداً',
    'change_due' => 'الباقي للعميل',
    'added_to_account' => 'يُضاف على الحساب',
    'confirm_sale' => 'تأكيد البيع',

    /* ---- completed ---- */
    'sale_done' => 'تم البيع',
    'print_receipt' => 'طباعة الإيصال',

    /* ---- shift ---- */
    'open_shift' => 'فتح وردية',
    'opening_float' => 'النقدية الافتتاحية في الدرج',
    'start_shift' => 'بدء الوردية',
    'change_terminal' => 'تغيير الكاشير',
    'shift_gate_note' => ':terminal. لا يمكن تسجيل أي فاتورة قبل فتح وردية، حتى تظل كل مبيعة قابلة للمراجعة في نهاية اليوم.',
    'shift_started' => 'بدأت :at',
    'invoice_count' => 'عدد الفواتير',
    'expected_in_drawer' => 'المتوقع في الدرج',
    'counted_cash' => 'النقدية المعدودة فعلياً',
    'variance' => 'الفرق',
    'notes_optional' => 'ملاحظات (اختياري)',
    'shift_closed' => 'تم إغلاق الوردية. الفرق: :variance',
    'back' => 'رجوع',

    /* ---- terminal picker ---- */
    'choose_terminal' => 'اختر الكاشير',
    'terminal_note' => 'يُحفظ الاختيار على هذا الجهاز، فلن تحتاج لتكراره في كل مرة. رقم الكاشير يظهر في أرقام الفواتير.',
    'no_terminals' => 'لا توجد أجهزة كاشير مفعّلة. أضف واحداً من لوحة التحكم أولاً.',
    'warehouse' => 'المخزن',
    'continue' => 'متابعة',

    /* ---- returns ---- */
    'return_title' => 'مرتجع',
    'find_receipt' => 'ابحث برقم الفاتورة',
    'receipt_number' => 'رقم الفاتورة',
    'search' => 'بحث',
    'receipt_not_found' => 'لا توجد فاتورة بهذا الرقم.',
    'original_sale' => 'الفاتورة الأصلية',
    'sold_at' => 'تاريخ البيع',
    'sold_qty' => 'الكمية المباعة',
    'already_returned' => 'مرتجع سابقاً',
    'returnable' => 'المتاح للإرجاع',
    'return_qty' => 'الكمية المرتجعة',
    'refund_total' => 'إجمالي المرتجع',
    'refund_method' => 'طريقة رد المبلغ',
    'refund_cash' => 'نقداً من الدرج',
    'refund_credit' => 'خصم من حساب العميل',
    'reason' => 'سبب الإرجاع (اختياري)',
    'confirm_return' => 'تأكيد المرتجع',
    'return_done' => 'تم تسجيل المرتجع',
    'refunded_amount' => 'المبلغ المرتجع',
    'fully_returned' => 'تم إرجاع هذه الفاتورة بالكامل.',
    'nothing_selected' => 'اختر كمية لإرجاعها أولاً.',

    /* ---- errors ---- */
    'no_terminal' => 'لم يتم اختيار الكاشير',
    'no_open_shift' => 'لا توجد وردية مفتوحة',
    'unknown_product' => 'منتج غير معروف',
    'no_product_with_plu' => 'لا يوجد منتج بكود PLU :plu',
    'zero_weight' => 'الباركود يحتوي على وزن صفر',
    'save_failed' => 'تعذّر حفظ الفاتورة',
    'credit_needs_customer' => 'البيع الآجل يتطلب اختيار عميل',
    'return_failed' => 'تعذّر تسجيل المرتجع',
    'cannot_return_a_return' => 'لا يمكن عمل مرتجع على فاتورة مرتجع.',
    'line_not_in_sale' => 'هذا الصنف ليس ضمن الفاتورة الأصلية.',
    'over_return' => 'الكمية المتاحة للإرجاع من «:product» هي :available فقط.',
    'nothing_to_return' => 'لم يتم اختيار أي كمية للإرجاع.',
    'credit_refund_needs_customer' => 'رد المبلغ على الحساب يتطلب عميلاً على الفاتورة.',

    /* ---- ledger descriptions ---- */
    'return_of' => 'مرتجع فاتورة :receipt',
    'refund_for' => 'رد مبلغ فاتورة :receipt',

    /* ---- receipt ---- */
    'receipt' => 'إيصال',
    'return_receipt' => 'إيصال مرتجع',
    'invoice' => 'الفاتورة',
    'date' => 'التاريخ',
    'terminal' => 'الكاشير',
    'seller' => 'البائع',
    'payment_method' => 'طريقة الدفع',
    'on_account' => 'على الحساب',
    'remaining' => 'الباقي',
    'thanks' => 'شكراً لتعاملكم مع نبته',
    'print' => 'طباعة',
    'back_to_till' => 'رجوع للكاشير',
];
