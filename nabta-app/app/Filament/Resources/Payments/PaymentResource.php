<?php

namespace App\Filament\Resources\Payments;

use App\Enums\Permission;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\EditPayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;





    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
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
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'edit' => EditPayment::route('/{record}/edit'),
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
        return __('المدفوعات والمقبوضات');
    }

    public static function getModelLabel(): string
    {
        return __('دفعة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المدفوعات والمقبوضات');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('الحسابات');
    }
}
