<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangeEmail extends Mailable
{

    use Queueable, SerializesModels;

    public $username;
    public $maskedPassword;

    public function __construct($username, $password)
    {
        $this->username = $username;
        $passwordLength = strlen($password);
        $this->maskedPassword = substr($password, 0, 1) . str_repeat('*', $passwordLength - 2) . substr($password, -1);
    }

    public function build()
    {
        return $this->subject('Your Password Has Been Changed!')
            ->view('passwordChanged');
    }
}
