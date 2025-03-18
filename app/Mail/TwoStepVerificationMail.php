<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TwoStepVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $username;
    public $otp;

    public function __construct($username, $otp)
    {
        $this->username = $username;
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('Your 2-Step Verification OTP')
            ->view('two_step_verification');
    }
}
