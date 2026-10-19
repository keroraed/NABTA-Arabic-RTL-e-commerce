<?php

namespace App\Filament\Resources\Expenses;

use App\Enums\Permission;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Resources\Expenses\Tables\ExpensesTable;
use App\Models\Expense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;





    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return ExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensesTable::configure($table);
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
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
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
        return __('المصروفات');
    }

    public static function getModelLabel(): string
    {
        return __('مصروف');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المصروفات');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('الحسابات');
    }
}
