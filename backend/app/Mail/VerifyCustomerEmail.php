<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyCustomerEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $verificationUrl,
        public readonly int $expirationHours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verifikasi Email Klinik Makmur Jaya');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verify-customer-email');
    }
}
