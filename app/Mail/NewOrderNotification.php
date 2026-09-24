<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewOrderNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        $customerName = $this->order->customer->name ?? 'Client';
        $orderId = $this->order->id;
        $total = number_format($this->order->total, 2);

        return $this->subject("🛒 [Maison I&M] Nouvelle commande #{$orderId} de {$customerName} ({$total} MAD)")
                    ->view('emails.new_order');
    }
}
