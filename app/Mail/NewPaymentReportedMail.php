<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class NewPaymentReportedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $payment;

    /**
     * Create a new message instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo Pago Reportado - Euro Rattan',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.reported', 
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // Verificamos que el pago tenga una imagen registrada
        if ($this->payment->proof_image) {
            
            // Extraemos la extensión original de la imagen (ej: jpg, png, pdf)
            $extension = pathinfo($this->payment->proof_image, PATHINFO_EXTENSION);
            
            // Adjuntamos el archivo leyéndolo directamente desde el disco 'public' de Storage
            $attachments[] = Attachment::fromStorageDisk('public', $this->payment->proof_image)
                    ->as('comprobante_pago_' . ($this->payment->order->code ?? 'desc') . '.' . $extension)
                    ->withMime('application/' . $extension); // Opcional, pero ayuda a los gestores de correo
        }

        return $attachments;
    }
}
