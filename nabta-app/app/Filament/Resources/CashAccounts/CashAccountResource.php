<?php

namespace App\Filament\Resources\CashAccounts;

use App\Enums\Permission;
use App\Filament\Resources\CashAccounts\Pages\CreateCashAccount;
use App\Filament\Resources\CashAccounts\Pages\EditCashAccount;
use App\Filament\Resources\CashAccounts\Pages\ListCashAccounts;
use App\Filament\Resources\CashAccounts\Schemas\CashAccountForm;
use App\Filament\Resources\CashAccounts\Tables\CashAccountsTable;
use App\Models\CashAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CashAccountResource extends Resource
{
    protected static ?string $model = CashAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;





    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CashAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashAccountsTable::configure($table);
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
            'index' => ListCashAccounts::route('/'),
            'create' => CreateCashAccount::route('/create'),
            'edit' => EditCashAccount::route('/{record}/edit'),
        ];
    }

    /* Access ------------------------------------------------------------
       Gated on App\Enums\Permission via the Gate abilities registered in
       AuthServiceProvider. Admins pass everything through Gate::before. */

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::AccountingView->value) ?? false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::AccountingManage->value) ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can(Permission::AccountingManage->value) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can(Permission::AccountingManage->value) ?? false;
    }
    public static function getNavigationLabel(): string
    {
        return __('الخزائن');
    }

    public static function getModelLabel(): string
    {
        return __('خزينة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الخزائن');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('الحسابات');
    }
}
