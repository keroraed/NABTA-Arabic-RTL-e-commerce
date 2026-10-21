{{-- "New order" email to the shop. Table layout and inline styles throughout:
     Gmail and Outlook strip <style> blocks and ignore flex/grid. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2).' ج.م';
    $placed = $order->placed_at?->copy()->timezone('Africa/Cairo');
    $label = 'padding:6px 0;color:#6B4630;font-size:13px;width:38%;vertical-align:top';
    $value = 'padding:6px 0;color:#0E2A1B;font-size:14px;font-weight:600;vertical-align:top';
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>طلب جديد {{ $order->order_number }}</title>
</head>
<body style="margin:0;padding:0;background:#F5EBD8;font-family:Tahoma,'IBM Plex Sans Arabic',Arial,sans-serif;direction:rtl">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5EBD8">
    <tr>
      <td align="center" style="padding:24px 12px">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#FFFFFF;border:1px solid #E6DCC8;border-radius:6px" dir="rtl">

          <tr>
            <td style="background:#0E2A1B;padding:22px 24px;border-radius:6px 6px 0 0;text-align:right">
              <p style="margin:0;color:#B08D57;font-size:13px">نبته NABTA — المتجر الإلكتروني</p>
              <h1 style="margin:6px 0 0;color:#F5EBD8;font-size:22px;font-weight:700">طلب جديد {{ $order->order_number }}</h1>
              <p style="margin:6px 0 0;color:#F5EBD8;font-size:13px;opacity:.85">
                {{ $placed?->format('Y/m/d') }} — الساعة {{ $placed?->format('h:i A') }} (بتوقيت القاهرة)
              </p>
            </td>
          </tr>

          {{-- Payment --}}
          <tr>
            <td style="padding:20px 24px 4px;text-align:right">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $order->isPaid() ? '#EAF4EE' : '#FBF5E9' }};border-radius:4px">
                <tr>
                  <td style="padding:12px 14px;font-size:14px;color:#0E2A1B;text-align:right">
                    طريقة الدفع: <strong>{{ $order->paymentMethodLabel() }}</strong><br>
                    حالة الدفع: <strong style="color:{{ $order->isPaid() ? '#176B3A' : '#8A5A12' }}">{{ $order->paymentStatusLabel() }}</strong>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- Customer --}}
          <tr>
            <td style="padding:18px 24px 0;text-align:right">
              <h2 style="margin:0 0 8px;font-size:16px;color:#0E2A1B;border-bottom:1px solid #EFE6D6;padding-bottom:8px">بيانات العميل والتوصيل</h2>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="rtl">
                <tr><td style="{{ $label }}">الاسم</td><td style="{{ $value }}">{{ $order->customer_name }}</td></tr>
                <tr><td style="{{ $label }}">رقم الموبايل</td><td style="{{ $value }}"><span dir="ltr">{{ $order->customer_phone }}</span></td></tr>
                @if ($order->customer_alt_phone)
                  <tr><td style="{{ $label }}">رقم بديل</td><td style="{{ $value }}"><span dir="ltr">{{ $order->customer_alt_phone }}</span></td></tr>
                @endif
                <tr><td style="{{ $label }}">المحافظة</td><td style="{{ $value }}">{{ $order->governorate_name_snapshot }}</td></tr>
                <tr><td style="{{ $label }}">المدينة / المنطقة</td><td style="{{ $value }}">{{ $order->city }}</td></tr>
                <tr><td style="{{ $label }}">العنوان</td><td style="{{ $value }}">{{ $order->address_line }}</td></tr>
                @if ($order->landmark)
                  <tr><td style="{{ $label }}">علامة مميزة</td><td style="{{ $value }}">{{ $order->landmark }}</td></tr>
                @endif
                @if ($order->notes)
                  <tr><td style="{{ $label }}">ملاحظات التوصيل</td><td style="{{ $value }};font-weight:400">{{ $order->notes }}</td></tr>
                @endif
              </table>
            </td>
          </tr>

          {{-- Items --}}
          <tr>
            <td style="padding:20px 24px 0;text-align:right">
              <h2 style="margin:0 0 8px;font-size:16px;color:#0E2A1B">المنتجات</h2>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="rtl" style="border-collapse:collapse;font-size:13px">
                <tr style="background:#F5EBD8">
                  <th align="right" style="padding:8px;color:#3A483D;font-weight:600">المنتج</th>
                  <th align="center" style="padding:8px;color:#3A483D;font-weight:600">سعر الوحدة</th>
                  <th align="center" style="padding:8px;color:#3A483D;font-weight:600">الكمية</th>
                  <th align="left" style="padding:8px;color:#3A483D;font-weight:600">الإجمالي</th>
                </tr>
                @foreach ($order->items as $item)
                  <tr>
                    <td style="padding:10px 8px;border-bottom:1px solid #EFE6D6;color:#0E2A1B">
                      <strong>{{ $item->name_ar }}</strong><br>
                      <span style="color:#6B4630;font-size:12px" dir="ltr">SKU: {{ $item->sku }}</span>
                    </td>
                    <td align="center" style="padding:10px 8px;border-bottom:1px solid #EFE6D6;white-space:nowrap">{{ $money($item->unit_price) }}</td>
                    <td align="center" style="padding:10px 8px;border-bottom:1px solid #EFE6D6">{{ $item->qty }}</td>
                    <td align="left" style="padding:10px 8px;border-bottom:1px solid #EFE6D6;white-space:nowrap;font-weight:600">{{ $money($item->line_total) }}</td>
                  </tr>
                @endforeach
              </table>
            </td>
          </tr>

          {{-- Totals --}}
          <tr>
            <td style="padding:14px 24px 0;text-align:right">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="rtl" style="font-size:14px">
                <tr><td style="padding:5px 0;color:#3A483D">المجموع الفرعي</td><td align="left" style="padding:5px 0;white-space:nowrap">{{ $money($order->subtotal) }}</td></tr>
                <tr><td style="padding:5px 0;color:#3A483D">الشحن</td><td align="left" style="padding:5px 0;white-space:nowrap">{{ (float) $order->shipping_fee === 0.0 ? 'مجاني' : $money($order->shipping_fee) }}</td></tr>
                <tr>
                  <td style="padding:10px 0 0;border-top:2px solid #E6DCC8;font-size:16px;font-weight:700;color:#0E2A1B">الإجمالي</td>
                  <td align="left" style="padding:10px 0 0;border-top:2px solid #E6DCC8;font-size:18px;font-weight:700;color:#176B3A;white-space:nowrap">{{ $money($order->total) }}</td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:24px">
              <a href="{{ $adminUrl }}" style="display:inline-block;background:#0E2A1B;color:#F5EBD8;text-decoration:none;font-size:15px;font-weight:600;padding:13px 28px;border-radius:3px">فتح الطلب في لوحة التحكم</a>
            </td>
          </tr>
        </table>

        <p style="margin:14px 0 0;color:#6B4630;font-size:12px">رسالة تلقائية من متجر نبته — لا ترد على هذا البريد.</p>
      </td>
    </tr>
  </table>
</body>
</html>
