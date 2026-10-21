<?php

namespace App\Filament\Resources\ExpenseCategories\Tables;

use App\Filament\Resources\ExpenseCategories\Pages\CreateExpenseCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ExpenseCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('اسم نوع المصروف'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('expenses_count')
                    ->label(__('عدد المصروفات'))
                    ->counts('expenses')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'primary' : 'gray')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('الحالة'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('تاريخ الإضافة'))
                    ->dateTime('d/m/Y - h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('آخر تعديل'))
                    ->dateTime('d/m/Y - h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('الحالة'))
                    ->placeholder(__('الكل'))
                    ->trueLabel(__('المفعّلة فقط'))
                    ->falseLabel(__('المعطّلة فقط')),
            ])
            ->recordActions([
                EditAction::make()->label(__('تعديل')),
                DeleteAction::make()->label(__('حذف')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label(__('حذف المحدد')),
                ]),
            ])
            ->emptyStateHeading(__('لا توجد أنواع مصروفات'))
            ->emptyStateDescription(__('أضف أنواع المصروفات (مثل إيجار، كهرباء، مرتبات) لتصنيف نفقاتك.'))
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make()->label(__('إضافة نوع مصروف')),
            ]);
    }
}
