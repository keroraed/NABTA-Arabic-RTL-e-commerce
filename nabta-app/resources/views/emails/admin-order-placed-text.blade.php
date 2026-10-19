@php($placed = $order->placed_at?->copy()->timezone('Africa/Cairo'))
طلب جديد {!! $order->order_number !!}
{!! $placed?->format('Y/m/d h:i A') !!} (بتوقيت القاهرة)

طريقة الدفع: {!! $order->paymentMethodLabel() !!}
حالة الدفع: {!! $order->paymentStatusLabel() !!}

العميل: {!! $order->customer_name !!}
الموبايل: {!! $order->customer_phone !!}
@if ($order->customer_alt_phone)
رقم بديل: {!! $order->customer_alt_phone !!}
@endif
المحافظة: {!! $order->governorate_name_snapshot !!}
المدينة: {!! $order->city !!}
العنوان: {!! $order->address_line !!}
@if ($order->landmark)
علامة مميزة: {!! $order->landmark !!}
@endif
@if ($order->notes)
ملاحظات: {!! $order->notes !!}
@endif

المنتجات:
@foreach ($order->items as $item)
- {!! $item->name_ar !!} ({!! $item->sku !!}) — {!! number_format((float) $item->unit_price, 2) !!} × {!! $item->qty !!} = {!! number_format((float) $item->line_total, 2) !!} ج.م
@endforeach

المجموع الفرعي: {!! number_format((float) $order->subtotal, 2) !!} ج.م
الشحن: {!! number_format((float) $order->shipping_fee, 2) !!} ج.م
الإجمالي: {!! number_format((float) $order->total, 2) !!} ج.م

{!! $adminUrl !!}
