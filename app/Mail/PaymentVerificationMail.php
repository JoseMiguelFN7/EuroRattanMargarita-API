<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PaymentVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30];

    public $payment;
    public $orderMessage;

    public function __construct(Payment $payment, $orderMessage)
    {
        // Aseguramos que las relaciones necesarias estén cargadas
        $this->payment = $payment->loadMissing(['order.user', 'currency', 'paymentMethod']);
        $this->orderMessage = $orderMessage;
    }

    public function envelope(): Envelope
    {
        $statusText = $this->payment->status === 'verified' ? 'Aprobado' : 'Rechazado';
        
        return new Envelope(
            subject: "Aviso de Pago {$statusText} - Orden #{$this->payment->order->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.payment_verification',
        );
    }

    public function failed(\Throwable $exception)
    {
        // Guardamos el error en el archivo storage/logs/laravel.log para revisarlo después
        Log::error(
            "Fallo definitivo al enviar correo de pago ID: {$this->payment->id} " .
            "para la orden {$this->payment->order->code}. Error: " . $exception->getMessage()
        );
    }
}