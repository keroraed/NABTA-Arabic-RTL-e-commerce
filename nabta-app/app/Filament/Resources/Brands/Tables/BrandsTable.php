<?php

namespace App\Filament\Resources\Brands\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('')
                    ->disk('assets')
                    ->square()
                    ->size(40),

                TextColumn::make('name_ar')
                    ->label(__('العلامة التجارية'))
                    ->searchable(['name_ar', 'name_en'])
                    ->sortable()
                    ->description(fn ($record) => $record->name_en),

                TextColumn::make('products_count')
                    ->label(__('عدد المنتجات'))
                    ->counts('products')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('slug')->label(__('الرابط'))->color('gray')->searchable(),

                ToggleColumn::make('is_active')->label(__('معروض')),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('معروض في المتجر')),
            ])
            ->recordActions([
                EditAction::make()->label(__('تعديل')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label(__('حذف')),
                ]),
            ])
            ->emptyStateHeading(__('لا توجد علامات تجارية بعد'));
    }
}
