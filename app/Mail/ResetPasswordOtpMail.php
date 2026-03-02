<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResetPasswordOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30];

    public $otp;

    public function __construct($otp) { $this->otp = $otp; }

    public function envelope(): Envelope {
        return new Envelope(subject: 'Recuperación de contraseña - Euro Rattan Margarita');
    }

    public function content(): Content {
        return new Content(view: 'emails.auth.reset_password');
    }

    public function failed(\Throwable $exception)
    {
        // Guardamos el error en el archivo storage/logs/laravel.log para revisarlo después
        Log::error(
            "Fallo definitivo al enviar correo de reinicio de contraseña." .
            " Error: " . $exception->getMessage()
        );
    }
}