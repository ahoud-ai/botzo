<?php 
// app/Helpers/Email.php

namespace App\Helpers;

use App\Mail\CustomEmail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Support\EmailTemplateRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Email
{
    public static function log($userId, $recipient, $subject, $body, $status, $attempts)
    {
        EmailLog::create([
            'user_id' => $userId,
            'recipient' => $recipient,
            'subject' => $subject,
            'message' => $body,
            'status' => $status,
            'attempts' => $attempts,
        ]);
    }

    public static function sendInvite($template, $recipientEmail, $inviter, $link, array $recipientContext = [])
    {
        if (!self::isEmailEnabled()) {
            return;
        }

        $emailTemplate = self::loadTemplate($template);
        if (!$emailTemplate || blank($recipientEmail)) {
            return;
        }

        $subject = self::replacePlaceholders(
            $emailTemplate->subject,
            self::inviteContext($recipientEmail, $inviter, $link, $recipientContext)
        );
        $body = self::replacePlaceholders(
            $emailTemplate->body,
            self::inviteContext($recipientEmail, $inviter, $link, $recipientContext)
        );

        try {
            Mail::to($recipientEmail)->queue(new CustomEmail($subject, $body));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public static function sendPasswordReset($template, $recipient, $link)
    {
        if (!self::isEmailEnabled()) {
            return;
        }

        $emailTemplate = self::loadTemplate($template);
        if (!$emailTemplate || !$recipient || blank($recipient->email ?? null)) {
            return;
        }

        $context = self::recipientContext($recipient, [
            'link' => $link,
        ]);
        $subject = self::replacePlaceholders($emailTemplate->subject, $context);
        $body = self::replacePlaceholders($emailTemplate->body, $context);

        try {
            Mail::to($recipient->email)->queue(new CustomEmail($subject, $body));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public static function sendSubscriptionEmail($template, $recipient, $plan)
    {
        if (!self::isEmailEnabled()) {
            return;
        }

        $emailTemplate = self::loadTemplate($template);
        if (!$emailTemplate || !$recipient || blank($recipient->email ?? null)) {
            return;
        }

        $context = self::recipientContext($recipient, [
            'plan' => $plan?->name,
        ]);
        $subject = self::replacePlaceholders($emailTemplate->subject, $context);
        $body = self::replacePlaceholders($emailTemplate->body, $context);

        try {
            Mail::to($recipient->email)->queue(new CustomEmail($subject, $body));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public static function send($template, $recipient, array $overrides = [])
    {
        if (!self::isEmailEnabled()) {
            return;
        }

        $emailTemplate = self::loadTemplate($template);
        if (!$emailTemplate || !$recipient || blank($recipient->email ?? null)) {
            return;
        }

        $context = self::recipientContext($recipient, $overrides);
        $subject = self::replacePlaceholders($emailTemplate->subject, $context);
        $body = self::replacePlaceholders($emailTemplate->body, $context);

        try {
            Mail::to($recipient->email)->queue(new CustomEmail($subject, $body));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    private static function isEmailEnabled(): bool
    {
        return (string) Setting::where('key', 'smtp_email_active')->value('value') === '1';
    }

    private static function loadTemplate(string $template): ?EmailTemplate
    {
        $emailTemplate = EmailTemplate::where('name', $template)->first();

        if (!$emailTemplate) {
            Log::warning('Email template was requested but not found.', [
                'template' => $template,
            ]);
        }

        return $emailTemplate;
    }

    public static function replacePlaceholders($content, array $context = [])
    {
        return EmailTemplateRenderer::render($content, $context);
    }

    public static function recipientContext($recipient = null, array $overrides = []): array
    {
        $firstName = trim((string) ($overrides['first_name'] ?? $recipient->first_name ?? ''));
        $lastName = trim((string) ($overrides['last_name'] ?? $recipient->last_name ?? ''));

        return array_merge([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $overrides['email'] ?? $recipient->email ?? null,
            'full_name' => trim((string) ($overrides['full_name'] ?? $recipient->full_name ?? trim(implode(' ', array_filter([$firstName, $lastName]))))),
            'company_name' => $overrides['company_name'] ?? Setting::query()->where('key', 'company_name')->value('value'),
        ], $overrides);
    }

    public static function defaultAppLink(): string
    {
        return url('/login');
    }

    private static function inviteContext(string $recipientEmail, $inviter, ?string $link, array $recipientContext = []): array
    {
        $context = self::recipientContext((object) [
            'first_name' => $recipientContext['first_name'] ?? $recipientContext['firstName'] ?? null,
            'last_name' => $recipientContext['last_name'] ?? $recipientContext['lastName'] ?? null,
            'email' => $recipientContext['email'] ?? $recipientEmail,
            'full_name' => $recipientContext['full_name'] ?? $recipientContext['fullName'] ?? null,
        ], array_merge($recipientContext, [
            'email' => $recipientContext['email'] ?? $recipientEmail,
            'link' => $link,
        ]));

        $context['invited_by_first_name'] = $inviter->first_name ?? null;
        $context['invited_by_last_name'] = $inviter->last_name ?? null;
        $context['invited_by_email'] = $inviter->email ?? null;
        $context['invited_by_full_name'] = $inviter->full_name ?? trim(implode(' ', array_filter([
            $inviter->first_name ?? null,
            $inviter->last_name ?? null,
        ])));

        return $context;
    }
}
