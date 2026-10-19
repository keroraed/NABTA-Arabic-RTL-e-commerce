<?php

namespace Tests\Feature;

use App\Mail\AdminOrderPlacedNotification;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Services\AdminOrderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class AdminOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The email is sent after the response; run it inline for assertions.
        $this->withoutDefer();

        config(['nabta.admin_notification_email' => 'shop@nabta.test']);
    }

    private function placeCodOrder(array $overrides = []): Order
    {
        $governorate = Governorate::factory()->create(['name_ar' => 'الجيزة', 'shipping_fee' => 45]);
        $product = Product::factory()->create([
            'name_ar' => 'زيت حبة البركة',
            'sku' => 'NBT-OIL-001',
            'price' => 120,
            'stock' => 10,
        ]);

        $this->withSession(['cart' => [$product->id => 3]])
            ->post(route('checkout.store'), array_merge([
                'customer_name' => 'محمود حسن',
                'customer_phone' => '01112345678',
                'customer_alt_phone' => '01212345678',
                'governorate_id' => $governorate->id,
                'city' => 'الدقي',
                'address_line' => 'شارع التحرير، عقار ٤٠، الدور الثاني',
                'landmark' => 'أمام البنك الأهلي',
                'notes' => 'الاتصال قبل الوصول',
            ], $overrides))
            ->assertRedirect();

        return Order::sole();
    }

    public function test_a_cod_order_emails_the_shop(): void
    {
        Mail::fake();

        $order = $this->placeCodOrder();

        Mail::assertSent(AdminOrderPlacedNotification::class, function (AdminOrderPlacedNotification $mail) use ($order) {
            return $mail->hasTo('shop@nabta.test') && $mail->order->is($order);
        });

        $this->assertNotNull($order->fresh()->admin_notified_at);
    }

    public function test_the_email_carries_everything_staff_need_to_fulfil_the_order(): void
    {
        Mail::fake();

        $order = $this->placeCodOrder();
        $mail = new AdminOrderPlacedNotification($order->load('items'));

        $mail->assertHasSubject("طلب جديد {$order->order_number} — 405.00 ج.م (الدفع عند الاستلام)");

        $mail->assertSeeInHtml($order->order_number);
        $mail->assertSeeInHtml('محمود حسن');
        $mail->assertSeeInHtml('01112345678');
        $mail->assertSeeInHtml('01212345678');
        $mail->assertSeeInHtml('الجيزة');
        $mail->assertSeeInHtml('الدقي');
        $mail->assertSeeInHtml('شارع التحرير، عقار ٤٠، الدور الثاني');
        $mail->assertSeeInHtml('أمام البنك الأهلي');
        $mail->assertSeeInHtml('الاتصال قبل الوصول');
        $mail->assertSeeInHtml('الدفع عند الاستلام');
        $mail->assertSeeInHtml('يُحصَّل عند الاستلام');
        $mail->assertSeeInHtml('زيت حبة البركة');
        $mail->assertSeeInHtml('NBT-OIL-001');
        $mail->assertSeeInHtml('120.00 ج.م');   // unit price
        $mail->assertSeeInHtml('360.00 ج.م');   // line total and subtotal
        $mail->assertSeeInHtml('45.00 ج.م');    // shipping
        $mail->assertSeeInHtml('405.00 ج.م');   // grand total
        $mail->assertSeeInHtml('dir="rtl"', false);

        $mail->assertSeeInText('NBT-OIL-001');
        $mail->assertSeeInText('405.00');
    }

    public function test_an_smtp_failure_never_breaks_the_checkout(): void
    {
        Log::spy();

        // Whatever the transport throws — bad credentials, host down — the
        // customer's order must still go through.
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection could not be established with host "smtp.example.com"'));

        $order = $this->placeCodOrder();

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(1, Order::count());

        // Released, so a later attempt for this order may still send it.
        $this->assertNull($order->fresh()->admin_notified_at);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => $message === 'Admin order notification email failed'
                && $context['order'] === $order->order_number
                && str_contains($context['error'], 'smtp.example.com'))
            ->once();
    }

    public function test_the_email_is_sent_at_most_once_per_order(): void
    {
        Mail::fake();

        $order = $this->placeCodOrder();

        // A second trigger — e.g. a retried Paymob callback — sends nothing.
        app(AdminOrderNotifier::class)->notify($order);

        Mail::assertSent(AdminOrderPlacedNotification::class, 1);
    }

    public function test_an_empty_recipient_setting_switches_the_email_off(): void
    {
        Mail::fake();
        config(['nabta.admin_notification_email' => '']);

        $this->placeCodOrder();

        Mail::assertNothingSent();
    }

    public function test_several_comma_separated_recipients_all_receive_it(): void
    {
        Mail::fake();
        config(['nabta.admin_notification_email' => 'owner@nabta.test, ops@nabta.test, not-an-email']);

        $this->placeCodOrder();

        Mail::assertSent(AdminOrderPlacedNotification::class, fn ($mail) => $mail->hasTo('owner@nabta.test')
            && $mail->hasTo('ops@nabta.test')
            && ! $mail->hasTo('not-an-email'));
    }
}
