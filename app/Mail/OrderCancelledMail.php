<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aviso Importante: Tu Orden ha sido Cancelada - Euro Rattan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.order_cancelled', 
        );
    }

    public function attachments(): array
    {
        return [];
    }
}