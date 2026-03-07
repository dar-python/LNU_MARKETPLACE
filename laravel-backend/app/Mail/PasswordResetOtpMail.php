<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $otp,
        public readonly CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'LNU Marketplace â€“ Password Reset OTP',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password_reset_otp',
            with: [
                'otp' => $this->otp,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
