<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class OrderInvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $order;
    public $invoice;

    public function __construct(Order $order, Invoice $invoice)
    {
        $this->order = $order;
        $this->invoice = $invoice;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Comprobante de Pago - Orden #' . $this->order->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.invoice',
        );
    }

    public function attachments(): array
    {
        // Adjuntamos el PDF directamente desde el disco public 
        // donde lo acabas de guardar en el Job
        return [
            Attachment::fromStorageDisk('public', $this->invoice->pdf_url)
                ->as('Factura_' . $this->invoice->invoice_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}