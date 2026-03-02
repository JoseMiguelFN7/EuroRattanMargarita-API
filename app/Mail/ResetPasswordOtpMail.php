<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    public $otp;

    public function __construct($otp) { $this->otp = $otp; }

    public function envelope(): Envelope {
        return new Envelope(subject: 'Recuperación de contraseña - Euro Rattan Margarita');
    }

    public function content(): Content {
        return new Content(view: 'emails.auth.reset_password');
    }
}