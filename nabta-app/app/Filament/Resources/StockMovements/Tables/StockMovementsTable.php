<?php

namespace App\Filament\Resources\StockMovements\Tables;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The full audit trail: every kilogram in and out, and what caused it.
 *
 * Read-only by construction — the table is append-only, and the model throws on
 * update or delete. A correction is a movement in the opposite direction.
 */
class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('التاريخ'))
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                TextColumn::make('product.name_ar')
                    ->label(__('المنتج'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label(__('المخزن'))
                    ->badge(),

                TextColumn::make('type')
                    ->label(__('نوع الحركة'))
                    ->badge()
                    ->formatStateUsing(fn (StockMovementType $state) => $state->label())
                    ->color(fn (StockMovementType $state) => match ($state) {
                        StockMovementType::PurchaseReceipt, StockMovementType::TransferIn,
                        StockMovementType::CustomerReturn => 'success',
                        StockMovementType::Shrinkage => 'warning',
                        StockMovementType::Adjustment => 'info',
                        default => 'danger',
                    }),

                TextColumn::make('quantity')
                    ->label(__('الكمية'))
                    ->formatStateUsing(fn ($state) => ((float) $state > 0 ? '+' : '')
                        .rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.'))
                    ->color(fn ($state) => (float) $state >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('notes')
                    ->label(__('المرجع'))
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('نوع الحركة'))
                    ->options(StockMovementType::options())
                    ->multiple(),

                SelectFilter::make('warehouse_id')
                    ->label(__('المخزن'))
                    ->relationship('warehouse', 'name'),

                SelectFilter::make('product_id')
                    ->label(__('المنتج'))
                    ->relationship('product', 'name_ar')
                    ->searchable()
                    ->preload(),

                // Shrinkage is normal for nuts and seeds — moisture loss,
                // roasting, repacking. It has its own type precisely so it never
                // pollutes the variance report used to find real problems.
                Filter::make('shrinkage')
                    ->label(__('الهالك الطبيعي فقط'))
                    ->query(fn (Builder $q) => $q->where('type', StockMovementType::Shrinkage)),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading(__('لا توجد حركات مخزون بعد'));
    }
}
