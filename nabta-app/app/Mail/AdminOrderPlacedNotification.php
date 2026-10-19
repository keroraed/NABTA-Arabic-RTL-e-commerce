<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "New order" email to the shop. Arabic, right-to-left. */
class AdminOrderPlacedNotification extends Mailable
{
    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'طلب جديد %s — %s ج.م (%s)',
                $this->order->order_number,
                number_format((float) $this->order->total, 2),
                $this->order->paymentMethodLabel(),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-order-placed',
            text: 'emails.admin-order-placed-text',
            with: [
                'order' => $this->order,
                'adminUrl' => route('filament.admin.resources.orders.view', $this->order),
            ],
        );
    }
}
