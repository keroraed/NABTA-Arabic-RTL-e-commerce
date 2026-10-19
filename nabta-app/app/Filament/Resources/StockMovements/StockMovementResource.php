<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\Permission;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\Schemas\StockMovementForm;
use App\Filament\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;





    protected static ?int $navigationSort = 2;

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

    public static function form(Schema $schema): Schema
    {
        return StockMovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
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
            'index' => ListStockMovements::route('/'),
        ];
    }

    /* Access ------------------------------------------------------------
       Gated on App\Enums\Permission via the Gate abilities registered in
       AuthServiceProvider. Admins pass everything through Gate::before. */

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::InventoryView->value) ?? false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }
    public static function getNavigationLabel(): string
    {
        return __('حركات المخزون');
    }

    public static function getModelLabel(): string
    {
        return __('حركة مخزون');
    }

    public static function getPluralModelLabel(): string
    {
        return __('حركات المخزون');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('المخزون');
    }
}
