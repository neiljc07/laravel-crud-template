<?php

namespace App\Mail;

use App\Models\ActivationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserActivation extends Mailable
{
    use Queueable, SerializesModels;

    public $activation_code;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(ActivationCode $activation_code)
    {
        $this->activation_code = $activation_code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Activation Code')
                    ->view('mails.user.activation')
                    ->text('mails.user.activation_plain');
    }
}
