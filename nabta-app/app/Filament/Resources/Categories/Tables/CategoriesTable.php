<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

                TextColumn::make('parent.name_ar')
                    ->label(__('القسم الرئيسي'))
                    ->badge()
                    ->color('gray')
                    ->placeholder(__('قسم رئيسي')),

                // Through the pivot: every product filed here, primary or not.
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
            ->filters([
                TernaryFilter::make('level')
                    ->label(__('المستوى'))
                    ->trueLabel(__('الأقسام الرئيسية'))
                    ->falseLabel(__('الأقسام الفرعية'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('parent_id'),
                        false: fn (Builder $query) => $query->whereNotNull('parent_id'),
                    ),

                SelectFilter::make('parent_id')
                    ->label(__('تابع للقسم'))
                    ->relationship('parent', 'name_ar', fn (Builder $query) => $query->whereNull('parent_id'))
                    ->preload(),
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
