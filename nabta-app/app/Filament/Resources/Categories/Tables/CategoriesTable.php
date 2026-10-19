<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name_ar')
                    ->label(__('القسم'))
                    ->searchable()
                    ->description(fn ($record) => $record->title_ar),

                TextColumn::make('products_count')
                    ->label(__('عدد المنتجات'))
                    ->counts('products')
                    ->badge()
                    // An empty category still renders a card on the categories
                    // page, so it should be obvious at a glance.
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'warning'),

                TextColumn::make('slug')->label(__('الرابط'))->color('gray')->searchable(),

                ToggleColumn::make('is_active')->label(__('معروض')),
            ])
            ->recordActions([
                EditAction::make()->label(__('تعديل')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label(__('حذف')),
                ]),
            ]);
    }
}
