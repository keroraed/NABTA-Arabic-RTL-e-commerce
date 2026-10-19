<?php

namespace App\Filament\Resources\ExpenseCategories;

use App\Enums\Permission;
use App\Filament\Resources\ExpenseCategories\Pages\CreateExpenseCategory;
use App\Filament\Resources\ExpenseCategories\Pages\EditExpenseCategory;
use App\Filament\Resources\ExpenseCategories\Pages\ListExpenseCategories;
use App\Filament\Resources\ExpenseCategories\Schemas\ExpenseCategoryForm;
use App\Filament\Resources\ExpenseCategories\Tables\ExpenseCategoriesTable;
use App\Models\ExpenseCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;





    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ExpenseCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseCategoriesTable::configure($table);
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
            'index' => ListExpenseCategories::route('/'),
            'create' => CreateExpenseCategory::route('/create'),
            'edit' => EditExpenseCategory::route('/{record}/edit'),
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
        return __('أنواع المصروفات');
    }

    public static function getModelLabel(): string
    {
        return __('نوع مصروف');
    }

    public static function getPluralModelLabel(): string
    {
        return __('أنواع المصروفات');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('الحسابات');
    }
}
