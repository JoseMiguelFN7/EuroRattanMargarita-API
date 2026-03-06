<?php

namespace App\Mail;

use App\Models\Commission;
use App\Models\CommissionSuggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSuggestionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $suggestion;
    public $commission;

    /**
     * Create a new message instance.
     */
    public function __construct(CommissionSuggestion $suggestion, Commission $commission)
    {
        $this->suggestion = $suggestion;
        $this->commission = $commission;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tienes un nuevo mensaje sobre tu Encargo - Euro Rattan',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.commissions.suggestion', 
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
