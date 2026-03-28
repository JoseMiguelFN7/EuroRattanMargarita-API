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
use Illuminate\Support\Facades\Log;

class OrderInvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30];

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
            subject: 'Comprobante Digital - Orden #' . $this->order->code,
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
                ->as('comprobante_' . $this->invoice->invoice_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }

    public function failed(\Throwable $exception)
    {
        // Guardamos el error en el archivo storage/logs/laravel.log para revisarlo después
        Log::error(
            "Fallo definitivo al enviar correo de comprobante: {$this->invoice->invoice_number} " .
            "para la orden {$this->order->code}. Error: " . $exception->getMessage()
        );
    }
}