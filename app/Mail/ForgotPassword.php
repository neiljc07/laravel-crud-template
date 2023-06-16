<?php

namespace App\Mail;

use App\Models\ForgotPasswordCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgotPassword extends Mailable
{
    use Queueable, SerializesModels;
    
    public $code;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(ForgotPasswordCode $code)
    {
        $this->code = $code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Forgot Password Code')
                    ->view('mails.user.forgot_password')
                    ->text('mails.user.forgot_password_plain');
    }
}
