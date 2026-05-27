<?php

namespace App\Mail;

use App\Helpers\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CustomEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    protected $user;
    protected $template;
    protected string $code;

    public function __construct($user, string $code)
    {
        $this->user = $user;
        $this->code = $code;
        $this->template = DB::table('email_templates')->where('name', 'Verify Email')->first();
    }

    public function build()
    {
        $context = Email::recipientContext($this->user, [
            'code' => $this->code,
            'link' => route('verification.notice'),
            'verification_link' => route('verification.notice'),
        ]);
        $subject = Email::replacePlaceholders($this->template?->subject ?? $this->defaultSubject(), $context);

        if (! preg_match('/\p{Arabic}/u', $subject)) {
            $subject .= ' | '.$this->arabicSubject();
        }
        $templateContent = Email::replacePlaceholders(
            $this->template?->body ?? '<p>{{Code}}</p>',
            $context
        );

        if (! str_contains($templateContent, $this->code)) {
            $templateContent .= '<p><strong>'.e(__('Verification code')).': '.$this->code.'</strong></p>';
        }

        return $this->subject($subject)
                    ->view('emails.custom_email_template')
                    ->with([
                        'body' => $templateContent,
                        'verificationCode' => $this->code,
                        'firstName' => $this->user->first_name,
                        'companyName' => $context['company_name'] ?: 'Botzo',
                        'logoUrl' => $this->logoUrl(),
                        'verificationUrl' => route('verification.notice'),
                    ]);
    }

    private function logoUrl(): ?string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        if ($baseUrl === '' || str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1')) {
            $baseUrl = 'https://botzo.net';
        }

        return $baseUrl.'/images/brand/botzo-favicon-app.png';
    }

    private function defaultSubject(): string
    {
        return 'Verify your email | '.$this->arabicSubject();
    }

    private function arabicSubject(): string
    {
        return "\u{062A}\u{0641}\u{0639}\u{064A}\u{0644} \u{0627}\u{0644}\u{0628}\u{0631}\u{064A}\u{062F} \u{0627}\u{0644}\u{0625}\u{0644}\u{0643}\u{062A}\u{0631}\u{0648}\u{0646}\u{064A}";
    }
}
