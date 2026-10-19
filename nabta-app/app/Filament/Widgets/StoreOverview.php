<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The numbers a COD shop opens the day on: who still needs a confirmation call,
 * what came in today, how much of it actually got delivered, and what is about
 * to sell out. Revenue counts delivered orders only — counting pending COD
 * orders as revenue overstates it badly, because a good share are refused at
 * the door.
 */
class StoreOverview extends StatsOverviewWidget
{
    public function getHeading(): ?string
    {
        return __('نظرة عامة');
    }

    protected function getStats(): array
    {
        $pending = Order::where('status', Order::STATUS_PENDING)->count();
        // An unpaid or failed Paymob checkout is not a sale yet.
        $todayCount = Order::whereDate('placed_at', today())
            ->whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_FAILED_PAYMENT])
            ->count();

        $delivered = Order::where('status', Order::STATUS_DELIVERED);
        $deliveredRevenue = (clone $delivered)->sum('total');

        $closed = Order::whereIn('status', [
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
            Order::STATUS_RETURNED,
        ])->count();

        $deliveredCount = (clone $delivered)->count();
        $successRate = $closed > 0 ? round($deliveredCount / $closed * 100) : null;

        $lowStock = Product::active()
            ->whereHas('stockLevels', fn ($q) => $q->where('quantity', '<=', 5))
            ->count();

        return [
            Stat::make(__('طلبات تنتظر التأكيد'), $pending)
                ->description($pending > 0 ? __('تحتاج مكالمة تأكيد') : __('لا يوجد ما ينتظر'))
                ->color($pending > 0 ? 'warning' : 'success'),

            Stat::make(__('طلبات اليوم'), $todayCount),

            Stat::make(__('إيراد الطلبات المسلَّمة'), number_format($deliveredRevenue, 0).' ج.م')
                ->description(__('الطلبات المسلَّمة فقط'))
                ->color('success'),

            Stat::make(__('نسبة نجاح التسليم'), $successRate === null ? '—' : $successRate.'%')
                ->description($closed > 0 ? __('من :count طلب مكتمل', ['count' => $closed]) : __('لا توجد طلبات مكتملة بعد'))
                ->color(match (true) {
                    $successRate === null => 'gray',
                    $successRate >= 80 => 'success',
                    $successRate >= 60 => 'warning',
                    default => 'danger',
                }),

            Stat::make(__('منتجات على وشك النفاد'), $lowStock)
                ->description(__('٥ قطع أو أقل'))
                ->color($lowStock > 0 ? 'warning' : 'success'),
        ];
    }
}
