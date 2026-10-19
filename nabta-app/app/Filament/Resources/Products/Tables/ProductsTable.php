<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('primary_image_path')
                    ->label('')
                    // The path is disk-relative ('products/x.webp'); binding the
                    // already-resolved URL here instead makes Filament check its
                    // existence against the 'public' disk (its default) and
                    // silently render nothing for every row.
                    ->disk('assets')
                    ->square()
                    ->size(46),

                TextColumn::make('name_ar')
                    ->label(__('المنتج'))
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->sku),

                TextColumn::make('category.name_ar')
                    ->label(__('القسم'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('price')
                    ->label(__('السعر'))
                    ->numeric(decimalPlaces: 0)
                    ->suffix(__(' ج.م'))
                    ->sortable(),

                // Derived from the stock ledger, not a column. withOnlineStock()
                // supplies it as one subquery so this stays sortable and does not
                // fire a query per row.
                TextColumn::make('online_stock')
                    ->label(__('المخزون'))
                    ->state(fn ($record) => $record->stock)
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.'))
                    ->sortable()
                    // The colour is the point: a stock list is scanned, not read.
                    ->color(fn ($state) => match (true) {
                        (float) $state <= 0 => 'danger',
                        (float) $state <= 5 => 'warning',
                        default => 'success',
                    })
                    ->weight('bold'),

                ToggleColumn::make('is_active')->label(__('معروض')),
                ToggleColumn::make('is_featured')->label(__('مميز')),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('القسم'))
                    ->relationship('category', 'name_ar')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')->label(__('معروض في المتجر')),

                Filter::make('low_stock')
                    ->label(__('مخزون منخفض أو منتهٍ'))
                    ->query(fn (Builder $query) => $query->whereHas(
                        'stockLevels',
                        fn (Builder $q) => $q->where('quantity', '<=', 5),
                    )),
            ])
            ->recordActions([
                EditAction::make()->label(__('تعديل')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label(__('حذف')),
                ]),
            ])
            ->emptyStateHeading(__('لا توجد منتجات بعد'));
    }
}
