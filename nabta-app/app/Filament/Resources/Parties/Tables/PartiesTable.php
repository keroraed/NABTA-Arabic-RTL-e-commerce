<?php

namespace App\Filament\Resources\Parties\Tables;

use App\Models\Party;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PartiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('الاسم'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Party $r) => $r->phone),

                TextColumn::make('type')
                    ->label(__('النوع'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __(Party::TYPE_LABELS[$state] ?? $state))
                    ->color(fn (string $state) => $state === Party::TYPE_SUPPLIER ? 'warning' : 'info'),

                // Derived from the ledger on every read, never stored. Positive
                // always means "money is owed", in whichever direction the party
                // type implies.
                TextColumn::make('balance')
                    ->label(__('الرصيد'))
                    ->state(fn (Party $r) => $r->balance())
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ج.م')
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->description(fn (Party $r) => $r->isSupplier() ? 'مستحق للمورد' : 'مستحق على العميل'),

                TextColumn::make('is_active')
                    ->label(__('نشط'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'نشط' : 'موقوف')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('النوع'))
                    ->options(collect(Party::TYPE_LABELS)->map(fn ($l) => __($l))->all()),

                TernaryFilter::make('is_active')->label(__('نشط')),
            ])
            ->recordActions([
                Action::make('statement')
                    ->label(__('كشف حساب'))
                    ->icon('heroicon-o-document-text')
                    ->url(fn (Party $record) => route('reports.party-statement', $record))
                    ->openUrlInNewTab(),
                EditAction::make()->label(__('تعديل')),
            ])
            ->emptyStateHeading(__('لا يوجد عملاء أو موردون بعد'));
    }
}
