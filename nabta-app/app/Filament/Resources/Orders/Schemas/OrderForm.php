<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Orders are read-only in admin.
 *
 * An order is a record of what a customer agreed to pay, and the totals were
 * computed and snapshotted server-side when it was placed. Letting staff retype
 * an amount or a line item would silently break the one guarantee the checkout
 * makes. Status is changed through the explicit transition actions on the table
 * instead, which is the only mutation the shop actually needs.
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('الطلب'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number')->label(__('رقم الطلب'))->weight('bold')->copyable(),
                        TextEntry::make('status')
                            ->label(__('الحالة'))
                            ->badge()
                            ->formatStateUsing(fn (string $state) => __(Order::STATUS_LABELS[$state] ?? $state)),
                        TextEntry::make('placed_at')->label(__('تاريخ الطلب'))->dateTime('Y/m/d H:i'),
                    ]),

                Section::make(__('الدفع'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('payment_method')
                            ->label(__('طريقة الدفع'))
                            ->formatStateUsing(fn (Order $record) => __($record->paymentMethodLabel())),
                        TextEntry::make('payment_status')
                            ->label(__('حالة الدفع'))
                            ->badge()
                            ->color(fn (Order $record) => $record->isPaid() ? 'success' : ($record->payment_status === Order::PAYMENT_FAILED ? 'danger' : 'gray'))
                            ->formatStateUsing(fn (Order $record) => __($record->paymentStatusLabel())),
                        TextEntry::make('paid_at')->label(__('تاريخ الدفع'))->dateTime('Y/m/d H:i')->placeholder('—'),
                        TextEntry::make('paymob_order_id')
                            ->label(__('رقم الطلب في Paymob'))
                            ->copyable()
                            ->placeholder('—')
                            ->visible(fn (Order $record) => $record->isPaymob()),
                        TextEntry::make('paymob_transaction_id')
                            ->label(__('رقم العملية في Paymob'))
                            ->copyable()
                            ->placeholder('—')
                            ->visible(fn (Order $record) => $record->isPaymob()),
                    ]),

                Section::make(__('العميل'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('customer_name')->label(__('الاسم')),
                        TextEntry::make('customer_phone')->label(__('رقم الموبايل'))->copyable(),
                        TextEntry::make('customer_alt_phone')->label(__('رقم بديل'))->copyable()->placeholder('—'),
                        TextEntry::make('governorate_name_snapshot')->label(__('المحافظة')),
                        TextEntry::make('city')->label(__('المدينة / المنطقة')),
                        TextEntry::make('landmark')->label(__('علامة مميزة'))->placeholder('—'),
                        TextEntry::make('address_line')->label(__('العنوان'))->columnSpanFull(),
                        TextEntry::make('notes')->label(__('ملاحظات العميل'))->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make(__('المنتجات'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('name_ar')->label(__('المنتج')),
                                TextEntry::make('sku')->label(__('الكود')),
                                TextEntry::make('unit_price')->label(__('سعر الوحدة'))->numeric(decimalPlaces: 2)->suffix(__(' ج.م')),
                                TextEntry::make('qty')->label(__('الكمية')),
                            ]),
                    ]),

                Section::make(__('المبالغ'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')->label(__('المجموع الفرعي'))->numeric(decimalPlaces: 2)->suffix(__(' ج.م')),
                        TextEntry::make('shipping_fee')->label(__('الشحن'))->numeric(decimalPlaces: 2)->suffix(__(' ج.م')),
                        TextEntry::make('total')->label(__('الإجمالي'))->numeric(decimalPlaces: 2)->suffix(__(' ج.م'))->weight('bold')->size('lg'),
                    ]),

                Section::make(__('سجل الحالة'))
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('confirmed_at')->label(__('تم التأكيد'))->dateTime('Y/m/d H:i')->placeholder('—'),
                        TextEntry::make('shipped_at')->label(__('تم الشحن'))->dateTime('Y/m/d H:i')->placeholder('—'),
                        TextEntry::make('delivered_at')->label(__('تم التسليم'))->dateTime('Y/m/d H:i')->placeholder('—'),
                        TextEntry::make('cancelled_at')->label(__('ملغي'))->dateTime('Y/m/d H:i')->placeholder('—'),
                        TextEntry::make('returned_at')->label(__('مرتجع'))->dateTime('Y/m/d H:i')->placeholder('—'),
                        TextEntry::make('cancel_reason')->label(__('السبب'))->placeholder('—'),
                    ]),
            ]);
    }
}
