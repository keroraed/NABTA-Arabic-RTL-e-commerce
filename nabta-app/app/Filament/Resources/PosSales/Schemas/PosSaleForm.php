<?php

namespace App\Filament\Resources\PosSales\Schemas;

use App\Models\PosSale;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * POS sales are read-only in admin.
 *
 * The sale already produced stock movements, a cash movement and possibly a
 * customer ledger entry, all in one transaction. Editing the row here would
 * decouple it from those consequences without reversing any of them, which is
 * how a set of books stops adding up.
 *
 * A mistake is corrected with a return, not by retyping the original sale.
 */
class PosSaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('الفاتورة'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('receipt_number')->label(__('رقم الفاتورة'))->weight('bold')->copyable(),
                        TextEntry::make('terminal.name')->label(__('الكاشير')),
                        TextEntry::make('soldBy.name')->label(__('البائع'))->placeholder('—'),
                        TextEntry::make('sold_at')->label(__('وقت البيع'))->dateTime('Y/m/d H:i'),
                    ]),

                Section::make(__('المنتجات'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('product_name_snapshot')->label(__('المنتج')),
                                TextEntry::make('unit_price_snapshot')
                                    ->label(__('السعر'))
                                    ->numeric(decimalPlaces: 2)
                                    ->suffix(__(' ج.م')),
                                TextEntry::make('quantity')
                                    ->label(__('الكمية'))
                                    ->state(fn ($record) => $record->weight_kg
                                        ? rtrim(rtrim(number_format((float) $record->weight_kg, 3), '0'), '.').' كجم'
                                        : rtrim(rtrim(number_format((float) $record->quantity, 3), '0'), '.')),
                                TextEntry::make('line_total')
                                    ->label(__('الإجمالي'))
                                    ->numeric(decimalPlaces: 2)
                                    ->suffix(__(' ج.م')),
                            ]),
                    ]),

                Section::make(__('المبالغ'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subtotal')->label(__('الإجمالي الفرعي'))->numeric(decimalPlaces: 2)->suffix(__(' ج.م')),
                        TextEntry::make('discount')->label(__('الخصم'))->numeric(decimalPlaces: 2)->suffix(__(' ج.م')),
                        TextEntry::make('total')->label(__('الإجمالي'))->numeric(decimalPlaces: 2)->suffix(__(' ج.م'))->weight('bold')->size('lg'),
                        TextEntry::make('payment_method')
                            ->label(__('طريقة الدفع'))
                            ->badge()
                            ->formatStateUsing(fn (string $state) => __(PosSale::PAYMENT_LABELS[$state] ?? $state)),

                        TextEntry::make('paid_cash')->label(__('المدفوع نقداً'))->numeric(decimalPlaces: 2)->suffix(__(' ج.م')),
                        TextEntry::make('paid_on_credit')->label(__('على الحساب'))->numeric(decimalPlaces: 2)->suffix(__(' ج.م')),
                        TextEntry::make('change_given')->label(__('الباقي'))->numeric(decimalPlaces: 2)->suffix(__(' ج.م')),
                        TextEntry::make('customer.name')->label(__('العميل'))->placeholder('—'),
                    ]),

                Section::make(__('المزامنة'))
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('received_at')->label(__('وصلت للخادم'))->dateTime('Y/m/d H:i')->placeholder('—'),
                        TextEntry::make('sync_lag')
                            ->label(__('التأخر'))
                            ->state(fn (PosSale $r) => $r->syncLagMinutes())
                            ->formatStateUsing(fn (?int $state) => $state === null ? '—' : $state.' دقيقة'),
                        // A badly-set terminal clock silently corrupts every
                        // report that groups by date, so the skew is visible.
                        TextEntry::make('clock_offset_ms')
                            ->label(__('انحراف ساعة الجهاز'))
                            ->formatStateUsing(fn (?int $state) => $state ? round($state / 60000).' دقيقة' : 'لا يوجد')
                            ->color(fn (?int $state) => abs((int) $state) > 300000 ? 'danger' : 'gray'),
                        TextEntry::make('has_total_discrepancy')
                            ->label(__('فرق بين إجمالي الجهاز والمحسوب'))
                            ->badge()
                            ->formatStateUsing(fn (bool $state) => $state ? 'نعم — يحتاج مراجعة' : 'لا')
                            ->color(fn (bool $state) => $state ? 'danger' : 'success'),
                    ]),
            ]);
    }
}
