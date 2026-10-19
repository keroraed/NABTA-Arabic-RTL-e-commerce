<?php

namespace App\Filament\Resources\PosTerminals;

use App\Enums\Permission;
use App\Filament\Resources\PosTerminals\Pages\CreatePosTerminal;
use App\Filament\Resources\PosTerminals\Pages\EditPosTerminal;
use App\Filament\Resources\PosTerminals\Pages\ListPosTerminals;
use App\Filament\Resources\PosTerminals\Schemas\PosTerminalForm;
use App\Filament\Resources\PosTerminals\Tables\PosTerminalsTable;
use App\Models\PosTerminal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PosTerminalResource extends Resource
{
    protected static ?string $model = PosTerminal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;





    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PosTerminalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PosTerminalsTable::configure($table);
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
            'index' => ListPosTerminals::route('/'),
            'create' => CreatePosTerminal::route('/create'),
            'edit' => EditPosTerminal::route('/{record}/edit'),
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

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can(Permission::SettingsManage->value) ?? false;
    }
    public static function getNavigationLabel(): string
    {
        return __('أجهزة الكاشير');
    }

    public static function getModelLabel(): string
    {
        return __('كاشير');
    }

    public static function getPluralModelLabel(): string
    {
        return __('أجهزة الكاشير');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('الكاشير');
    }
}
