<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Order numbers are generated server-side. The prototype's client-side
 * "NB-" + timestamp fallback could collide and was customer-controlled.
 */
class OrderNumberGenerator
{
    private const PREFIX = 'NB-';
    private const START = 10000;

    public function next(): string
    {
        // Runs inside the order transaction; the row lock serialises concurrent
        // checkouts so two orders can never take the same number.
        $last = DB::table('orders')
            ->where('order_number', 'like', self::PREFIX.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('order_number');

        $n = $last ? ((int) substr($last, strlen(self::PREFIX))) + 1 : self::START;

        while (Order::where('order_number', self::PREFIX.$n)->exists()) {
            $n++;
        }

        return self::PREFIX.$n;
    }
}
