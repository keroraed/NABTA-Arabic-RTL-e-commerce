<?php

namespace App\Filament\Resources\Governorates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class GovernoratesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->paginated([25, 50, 'all'])
            ->columns([
                TextColumn::make('name_ar')->label(__('المحافظة'))->searchable()->sortable(),

                // Editable in place: updating a courier's price list is a pass over
                // 27 rows, not 27 visits to an edit form.
                TextInputColumn::make('shipping_fee')
                    ->label(__('سعر الشحن (ج.م)'))
                    ->rules(['required', 'numeric', 'min:0'])
                    ->sortable(),

                ToggleColumn::make('is_active')->label(__('متاح')),
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
