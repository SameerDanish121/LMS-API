<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgetPasswordEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $username;

    public function __construct($username, $otp)
    {
        $this->otp = $otp;
        $this->username = $username;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Password Reset OTP"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'ForgetPassword',
            with: [
                'otp' => $this->otp,
                'username' => $this->username
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
