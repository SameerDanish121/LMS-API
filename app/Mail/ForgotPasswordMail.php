<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable
{
    use Queueable, SerializesModels;
    public $resetLink;
    /**
     * Create a new message instance.
     */
    public function __construct()
    {
        //
    }
    public function build()
    {
        return $this->subject('Reset Your Password')
                    ->from('biit@edu.com', 'LMS') // Replace with your email
                    ->withSwiftMessage(function ($message) {
                        $message->getHeaders()
                            ->addTextHeader('Forget Password', 'sameer@biit');
                    })
                    ->setBody(
                        "Hi,\n\nClick the link below to reset your password:\n\n\n\nIf you didn't request a password reset, please ignore this email.",
                        'text/plain'
                    );
    }
}
