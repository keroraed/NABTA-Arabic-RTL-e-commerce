<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('placed_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label(__('رقم الطلب'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('customer_name')
                    ->label(__('العميل'))
                    ->searchable()
                    ->description(fn (Order $record) => $record->customer_phone),

                TextColumn::make('governorate_name_snapshot')
                    ->label(__('المحافظة'))
                    ->searchable()
                    ->description(fn (Order $record) => $record->city),

                TextColumn::make('total')
                    ->label(__('الإجمالي'))
                    ->numeric(decimalPlaces: 0)
                    ->suffix(__(' ج.م'))
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('الحالة'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __(Order::STATUS_LABELS[$state] ?? $state))
                    ->color(fn (string $state) => match ($state) {
                        Order::STATUS_PENDING => 'warning',
                        Order::STATUS_CONFIRMED => 'info',
                        Order::STATUS_SHIPPED => 'primary',
                        Order::STATUS_DELIVERED => 'success',
                        Order::STATUS_CANCELLED, Order::STATUS_RETURNED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('placed_at')
                    ->label(__('تاريخ الطلب'))
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('الحالة'))
                    ->options(collect(Order::STATUS_LABELS)->map(fn ($l) => __($l))->all())
                    ->multiple(),

                SelectFilter::make('governorate_id')
                    ->label(__('المحافظة'))
                    ->relationship('governorate', 'name_ar')
                    ->searchable()
                    ->preload(),

                Filter::make('needs_call')
                    ->label(__('في انتظار مكالمة التأكيد'))
                    ->query(fn (Builder $query) => $query->where('status', Order::STATUS_PENDING))
                    ->default(),
            ])
            ->recordActions([
                ViewAction::make()->label(__('عرض')),
                ActionGroup::make(static::transitionActions())->label(__('تغيير الحالة'))->button(),
            ])
            ->emptyStateHeading(__('لا توجد طلبات بعد'));
    }

    /**
     * One action per status transition, each visible only where the order's
     * current status actually allows it. Statuses are never a free-text dropdown:
     * an order cannot jump from pending straight to delivered, and every change
     * stamps its own timestamp column.
     *
     * @return array<int, Action>
     */
    private static function transitionActions(): array
    {
        $timestamps = [
            Order::STATUS_CONFIRMED => 'confirmed_at',
            Order::STATUS_SHIPPED => 'shipped_at',
            Order::STATUS_DELIVERED => 'delivered_at',
            Order::STATUS_CANCELLED => 'cancelled_at',
            Order::STATUS_RETURNED => 'returned_at',
        ];

        return collect(Order::STATUS_LABELS)
            ->except(Order::STATUS_PENDING)
            ->map(function (string $label, string $status) use ($timestamps) {
                $needsReason = in_array($status, [Order::STATUS_CANCELLED, Order::STATUS_RETURNED], true);

                return Action::make("to_{$status}")
                    ->label($label)
                    ->icon(match ($status) {
                        Order::STATUS_CONFIRMED => 'heroicon-o-phone',
                        Order::STATUS_SHIPPED => 'heroicon-o-truck',
                        Order::STATUS_DELIVERED => 'heroicon-o-check-circle',
                        default => 'heroicon-o-x-circle',
                    })
                    ->color($needsReason ? 'danger' : 'primary')
                    ->visible(fn (Order $record) => $record->canTransitionTo($status))
                    ->requiresConfirmation()
                    ->schema($needsReason ? [
                        TextInput::make('cancel_reason')
                            ->label(__('السبب'))
                            ->required()
                            ->maxLength(255),
                    ] : [])
                    ->action(function (Order $record, array $data) use ($status, $timestamps) {
                        // Re-check on write: the row may have moved on in another tab
                        // between the page rendering and the click landing.
                        if (! $record->canTransitionTo($status)) {
                            Notification::make()
                                ->title(__('تغيّرت حالة الطلب بالفعل'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => $status,
                            $timestamps[$status] => now(),
                            'cancel_reason' => $data['cancel_reason'] ?? $record->cancel_reason,
                        ]);

                        Notification::make()
                            ->title(__('تم تحديث حالة الطلب'))
                            ->success()
                            ->send();
                    });
            })
            ->values()
            ->all();
    }
}
