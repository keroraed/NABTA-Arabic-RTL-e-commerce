<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\CartChangedException;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Creates an order from the session cart.
 *
 * Everything monetary is recomputed here from the database. Nothing the browser
 * sent about prices, fees or totals is trusted or stored.
 *
 * Stock leaves through the movement ledger, not a column decrement, so an online
 * order and a POS sale are the same kind of event against the same warehouse
 * balance. The storefront draws from the warehouse flagged `is_online_fulfilment`.
 */
class OrderPlacer
{
    public function __construct(
        private readonly Cart $cart,
        private readonly ShippingCalculator $shipping,
        private readonly OrderNumberGenerator $numbers,
        private readonly StockLedger $stock,
    ) {}

    /**
     * @param  array{customer_name:string,customer_phone:string,customer_alt_phone:?string,governorate_id:int,city:string,address_line:string,landmark:?string,notes:?string,ip_address:?string}  $data
     *
     * @throws CartChangedException when the cart no longer reflects what the
     *         customer saw — an item sold out or was deactivated mid-checkout.
     */
    public function place(array $data): Order
    {
        // Deliberately the STORED quantities, not Cart::lines(). lines() clamps to
        // current stock for display, and checking out against the clamped figure
        // would quietly hand the customer fewer items than they asked for. Here we
        // want the mismatch to surface so they can be told.
        $wanted = $this->cart->raw();

        if ($wanted === []) {
            throw new CartChangedException('سلتك فارغة.');
        }

        $governorate = Governorate::active()->findOrFail($data['governorate_id']);
        $warehouse = Warehouse::onlineFulfilment();

        return DB::transaction(function () use ($wanted, $governorate, $data, $warehouse) {
            $subtotal = 0.0;
            $items = [];
            $movements = [];

            foreach ($wanted as $productId => $qty) {
                /** @var Product|null $product */
                $product = Product::query()
                    ->whereKey($productId)
                    ->lockForUpdate()
                    ->first();

                if (! $product || ! $product->is_active) {
                    throw new CartChangedException('لم يعد أحد المنتجات في سلتك متاحاً. من فضلك راجع السلة.');
                }

                // Unlike the POS, the storefront DOES hold the line on stock: the
                // customer is not standing at the counter holding the goods, and
                // promising something that is not there costs a delivery run.
                $onHand = $product->stockAt($warehouse);

                if ($onHand < $qty) {
                    throw new CartChangedException(
                        $onHand > 0
                            ? "الكمية المتاحة من «{$product->name_ar}» أصبحت ".$this->format($onHand).' فقط.'
                            : "نفدت الكمية من «{$product->name_ar}»."
                    );
                }

                $unitPrice = (float) $product->price;
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $items[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name_ar' => $product->name_ar,
                    'unit_price' => $unitPrice,
                    'qty' => $qty,
                    'line_total' => $lineTotal,
                ];

                $movements[] = ['product' => $product, 'qty' => $qty];
            }

            $subtotal = round($subtotal, 2);
            $shippingFee = $this->shipping->feeFor($governorate, $subtotal);

            $order = Order::create([
                'order_number' => $this->numbers->next(),
                'status' => Order::STATUS_PENDING,
                'payment_method' => 'cod',
                'channel' => 'web',
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_alt_phone' => $data['customer_alt_phone'] ?? null,
                'governorate_id' => $governorate->id,
                'governorate_name_snapshot' => $governorate->name_ar,
                'city' => $data['city'],
                'address_line' => $data['address_line'],
                'landmark' => $data['landmark'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'total' => round($subtotal + $shippingFee, 2),
                'currency' => 'EGP',
                'ip_address' => $data['ip_address'] ?? null,
                'placed_at' => now(),
            ]);

            $order->items()->createMany($items);

            // After the order exists, so each movement can reference it.
            if ($warehouse) {
                foreach ($movements as $movement) {
                    $this->stock->record(
                        product: $movement['product'],
                        warehouse: $warehouse,
                        type: StockMovementType::OnlineSale,
                        quantity: $movement['qty'],
                        reference: $order,
                        notes: 'طلب '.$order->order_number,
                    );
                }
            }

            return $order;
        });
    }

    /** Whole numbers read better than "3.000 فقط" in a customer-facing message. */
    private function format(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
