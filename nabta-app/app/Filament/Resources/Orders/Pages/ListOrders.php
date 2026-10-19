<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * The shop works its orders by status, so the statuses are tabs rather than a
     * filter dropdown: "who do I still have to call" is one click, not three.
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(__('الكل')),
        ];

        foreach (Order::STATUS_LABELS as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status))
                ->badge(Order::where('status', $status)->count())
                ->badgeColor($status === Order::STATUS_PENDING ? 'warning' : null);
        }

        return $tabs;
    }
}
