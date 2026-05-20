<?php

namespace App\Mail;

use App\Helpers\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;

class CustomEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    protected $user;
    protected $template;

    public function __construct($user)
    {
        $this->user = $user;
        $this->template = DB::table('email_templates')->where('name', 'Verify Email')->first();
    }

    public function build()
    {
        $verificationUrl = $this->verificationUrl($this->user);
        $context = Email::recipientContext($this->user, [
            'link' => $verificationUrl,
            'verification_link' => $verificationUrl,
        ]);
        $subject = Email::replacePlaceholders(
            $this->template?->subject ?? __('Please verify your email'),
            $context
        );
        $templateContent = Email::replacePlaceholders(
            $this->template?->body ?? '<p>{{Link}}</p>',
            $context
        );

        return $this->subject($subject)
                    ->view('emails.custom_email_template')
                    ->with([
                        'body' => $templateContent,
                    ]);
    }

    protected function verificationUrl($user)
    {
        return URL::temporarySignedRoute(
            'verification.verify', 
            now()->addMinutes(60), 
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        );
    }
}
