<?php

namespace App\Support;

use App\Models\Setting;

class EmailTemplateRenderer
{
    public static function render(?string $content, array $context = []): string
    {
        $content = (string) ($content ?? '');

        if ($content === '') {
            return '';
        }

        $replacements = self::replacementMap($context);

        foreach ($replacements as $token => $value) {
            $value = (string) $value;

            foreach (self::tokenVariants($token) as $variant) {
                $content = str_replace($variant, $value, $content);
            }
        }

        return $content;
    }

    public static function renderPair(?string $subject, ?string $body, array $context = []): array
    {
        return [
            'subject' => self::render($subject, $context),
            'body' => self::render($body, $context),
        ];
    }

    private static function replacementMap(array $context): array
    {
        $companyName = trim((string) ($context['company_name'] ?? ''));
        if ($companyName === '') {
            $companyName = (string) (Setting::query()->where('key', 'company_name')->value('value') ?? '');
        }

        $firstName = self::stringValue($context, ['first_name', 'firstName']);
        $lastName = self::stringValue($context, ['last_name', 'lastName']);
        $email = self::stringValue($context, ['email']);
        $fullName = trim((string) ($context['full_name'] ?? $context['fullName'] ?? trim(implode(' ', array_filter([$firstName, $lastName])))));
        $invitedByFirstName = self::stringValue($context, ['invited_by_first_name', 'invitedByFirstName']);
        $invitedByLastName = self::stringValue($context, ['invited_by_last_name', 'invitedByLastName']);
        $invitedByEmail = self::stringValue($context, ['invited_by_email', 'invitedByEmail']);
        $invitedByFullName = trim((string) ($context['invited_by_full_name'] ?? $context['invitedByFullName'] ?? trim(implode(' ', array_filter([$invitedByFirstName, $invitedByLastName])))));

        return [
            'FirstName' => $firstName,
            'LastName' => $lastName,
            'Email' => $email,
            'FullName' => $fullName,
            'Link' => self::stringValue($context, ['link', 'verification_link', 'verificationLink', 'reset_link', 'resetLink']),
            'plan' => self::stringValue($context, ['plan', 'plan_name', 'planName']),
            'CompanyName' => $companyName,
            'InvitedByFirstName' => $invitedByFirstName,
            'InvitedByLastName' => $invitedByLastName,
            'InvitedByEmail' => $invitedByEmail,
            'InvitedByFullName' => $invitedByFullName,
        ];
    }

    private static function tokenVariants(string $token): array
    {
        $camelToken = lcfirst($token);
        $variants = [
            '{{'.$token.'}}',
            '{'.$camelToken.'}',
        ];

        if ($token === 'Link') {
            $variants[] = '{verificationLink}';
            $variants[] = '{resetLink}';
        }

        $encodedVariants = [];
        foreach ($variants as $variant) {
            $encodedVariants[] = rawurlencode($variant);
            $encodedVariants[] = rawurlencode(rawurlencode($variant));
        }

        return array_values(array_unique(array_merge($variants, $encodedVariants)));
    }

    private static function stringValue(array $context, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $context) && $context[$key] !== null) {
                return trim((string) $context[$key]);
            }
        }

        return '';
    }
}
