<?php

namespace App\Filament\Resources\PosSales;

use App\Enums\Permission;
use App\Filament\Resources\PosSales\Pages\ListPosSales;
use App\Filament\Resources\PosSales\Pages\ViewPosSale;
use App\Filament\Resources\PosSales\Schemas\PosSaleForm;
use App\Filament\Resources\PosSales\Tables\PosSalesTable;
use App\Models\PosSale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PosSaleResource extends Resource
{
    protected static ?string $model = PosSale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;





    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'receipt_number';

    /**
     * Append-only record. It is written by the till and by the ledger
     * services, never typed in by hand — editing it would silently
     * decouple the row from the movements and money it produced.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PosSaleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PosSalesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosSales::route('/'),
            'view' => ViewPosSale::route('/{record}'),
        ];
    }

    /* Access ------------------------------------------------------------
       Gated on App\Enums\Permission via the Gate abilities registered in
       AuthServiceProvider. Admins pass everything through Gate::before. */

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ReportsView->value) ?? false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }
    public static function getNavigationLabel(): string
    {
        return __('مبيعات الكاشير');
    }

    public static function getModelLabel(): string
    {
        return __('فاتورة كاشير');
    }

    public static function getPluralModelLabel(): string
    {
        return __('مبيعات الكاشير');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('الكاشير');
    }
}
