<?php

namespace App\Filament\Resources\PosSales\Tables;

use App\Models\PosSale;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PosSalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sold_at', 'desc')
            ->columns([
                TextColumn::make('receipt_number')
                    ->label(__('رقم الفاتورة'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('terminal.code')
                    ->label(__('الكاشير'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('total')
                    ->label(__('الإجمالي'))
                    ->numeric(decimalPlaces: 2)
                    ->suffix(__(' ج.م'))
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label(__('الإجمالي'))),

                TextColumn::make('payment_method')
                    ->label(__('الدفع'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __(PosSale::PAYMENT_LABELS[$state] ?? $state))
                    ->color(fn (string $state) => match ($state) {
                        PosSale::CASH => 'success',
                        PosSale::CREDIT => 'warning',
                        default => 'info',
                    }),

                TextColumn::make('customer.name')
                    ->label(__('العميل'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('sold_at')
                    ->label(__('وقت البيع'))
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                // A large gap between the sale and its arrival means the terminal
                // was offline. Surfacing it is how connectivity problems get
                // noticed before a whole day goes missing.
                TextColumn::make('sync_lag')
                    ->label(__('تأخر الوصول'))
                    ->state(fn (PosSale $r) => $r->syncLagMinutes())
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : ($state < 1 ? 'فوري' : $state.' د'))
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state > 720 => 'danger',
                        $state > 120 => 'warning',
                        default => 'success',
                    })
                    ->toggleable(),

                IconColumn::make('has_total_discrepancy')
                    ->label(__('فرق في الإجمالي'))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('pos_terminal_id')
                    ->label(__('الكاشير'))
                    ->relationship('terminal', 'code'),

                SelectFilter::make('payment_method')
                    ->label(__('طريقة الدفع'))
                    ->options(collect(PosSale::PAYMENT_LABELS)->map(fn ($l) => __($l))->all()),

                Filter::make('today')
                    ->label(__('اليوم فقط'))
                    ->query(fn (Builder $q) => $q->whereDate('sold_at', today())),

                // The server recomputes every line; this is where a mismatch
                // between what the till said and what the prices say shows up.
                Filter::make('discrepancy')
                    ->label(__('فيها فرق في الإجمالي'))
                    ->query(fn (Builder $q) => $q->where('has_total_discrepancy', true)),

                Filter::make('late')
                    ->label(__('وصلت متأخرة (أكثر من ساعتين)'))
                    ->query(fn (Builder $q) => $q->whereRaw(
                        'received_at IS NOT NULL AND sold_at IS NOT NULL AND received_at > '.
                        (config('database.default') === 'sqlite'
                            ? "datetime(sold_at, '+120 minutes')"
                            : 'DATE_ADD(sold_at, INTERVAL 120 MINUTE)')
                    )),
            ])
            ->recordActions([
                ViewAction::make()->label(__('عرض')),
                Action::make('receipt')
                    ->label(__('الإيصال'))
                    ->icon('heroicon-o-printer')
                    ->url(fn (PosSale $record) => route('pos.receipt', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading(__('لا توجد مبيعات كاشير بعد'));
    }
}
