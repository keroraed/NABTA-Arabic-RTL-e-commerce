<?php

namespace App\Filament\Resources\Orders;

use App\Enums\Permission;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;





    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'order_number';

    /**
     * Orders are created by customers at checkout and never by staff — there is
     * no admin path that could recompute the totals safely — and they are never
     * edited, only transitioned. See OrderForm.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    /** The count of orders still waiting on the confirmation call. */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', Order::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    /* Access ------------------------------------------------------------
       Gated on App\Enums\Permission via the Gate abilities registered in
       AuthServiceProvider. Admins pass everything through Gate::before. */

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::OrdersManage->value) ?? false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }
    public static function getNavigationLabel(): string
    {
        return __('الطلبات');
    }

    public static function getModelLabel(): string
    {
        return __('طلب');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الطلبات');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('الطلبات');
    }
}
