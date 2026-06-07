<?php

namespace App\Mail;

use App\Helpers\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $user;
    protected string $code;
    protected $template;

    public function __construct($user, string $code)
    {
        $this->user = $user;
        $this->code = $code;
        $this->template = DB::table('email_templates')->where('name', 'Reset Password')->first();
    }

    public function build()
    {
        $context = Email::recipientContext($this->user, [
            'code' => $this->code,
        ]);

        $subject = Email::replacePlaceholders(
            $this->template?->subject ?? $this->defaultSubject(),
            $context
        );

        if (! preg_match('/\p{Arabic}/u', $subject)) {
            $subject .= ' | ' . $this->arabicSubject();
        }

        return $this->subject($subject)
            ->view('emails.custom_email_template')
            ->with([
                'verificationCode' => $this->code,
                'firstName' => $this->user->first_name ?? '',
                'companyName' => $context['company_name'] ?: 'Botzo',
                'logoUrl' => $this->logoUrl(),
                'isPasswordReset' => true,
            ]);
    }

    private function logoUrl(): ?string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        if ($baseUrl === '' || str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1')) {
            $baseUrl = 'https://botzo.net';
        }

        return $baseUrl . '/images/brand/botzo-favicon-app.png';
    }

    private function defaultSubject(): string
    {
        return 'Reset your password';
    }

    private function arabicSubject(): string
    {
        return 'إعادة تعيين كلمة المرور';
    }
}
