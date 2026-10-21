<?php

namespace App\Filament\Resources\Expenses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expense_date', 'desc')
            ->columns([
                TextColumn::make('expense_date')
                    ->label(__('تاريخ المصروف'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label(__('نوع المصروف'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('amount')
                    ->label(__('المبلغ'))
                    ->numeric(2)
                    ->suffix(' ' . __('ج.م'))
                    ->sortable()
                    ->weight('bold')
                    ->summarize([
                        Sum::make()
                            ->label(__('الإجمالي'))
                            ->suffix(' ' . __('ج.م')),
                    ]),

                TextColumn::make('cashAccount.name')
                    ->label(__('خزينة الصرف'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('warehouse.name')
                    ->label(__('الفرع / المخزن'))
                    ->placeholder(__('عام'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label(__('البيان'))
                    ->searchable()
                    ->limit(40)
                    ->placeholder(__('—'))
                    ->tooltip(fn ($record) => $record->description),

                TextColumn::make('attachment_path')
                    ->label(__('المرفق'))
                    ->state(fn ($record) => $record->attachment_path ? __('عرض المرفق') : null)
                    ->url(fn ($record) => $record->attachment_path ? asset('storage/' . $record->attachment_path) : null, shouldOpenInNewTab: true)
                    ->icon(fn ($record) => $record->attachment_path ? 'heroicon-o-paper-clip' : null)
                    ->color('primary')
                    ->placeholder(__('بدون مرفق')),

                TextColumn::make('creator.name')
                    ->label(__('سُجّل بواسطة'))
                    ->placeholder(__('—'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('تاريخ التسجيل'))
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
                SelectFilter::make('expense_category_id')
                    ->label(__('نوع المصروف'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('cash_account_id')
                    ->label(__('خزينة الصرف'))
                    ->relationship('cashAccount', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('warehouse_id')
                    ->label(__('الفرع / المخزن'))
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('expense_date')
                    ->label(__('فترة تاريخ المصروف'))
                    ->form([
                        DatePicker::make('from')
                            ->label(__('من تاريخ'))
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label(__('إلى تاريخ'))
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('expense_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('expense_date', '<=', $date));
                    }),
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
            ->emptyStateHeading(__('لا توجد مصروفات'))
            ->emptyStateDescription(__('لم يتم تسجيل أي مصروفات حتى الآن. يمكنك إضافة مصروف جديد لتسجيل النفقات.'))
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make()->label(__('إضافة مصروف جديد')),
            ]);
    }
}
