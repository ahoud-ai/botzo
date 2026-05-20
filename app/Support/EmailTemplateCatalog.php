<?php

namespace App\Support;

class EmailTemplateCatalog
{
    private const BASE_TOKENS = [
        'FirstName',
        'LastName',
        'Email',
        'FullName',
        'CompanyName',
    ];

    private const DEFINITIONS = [
        'Registration' => [
            'editor' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'CompanyName',
            ],
            'allowed' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'CompanyName',
                'Link',
            ],
        ],
        'Invite' => [
            'editor' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'Link',
                'CompanyName',
                'InvitedByFirstName',
                'InvitedByLastName',
                'InvitedByEmail',
                'InvitedByFullName',
            ],
            'allowed' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'Link',
                'CompanyName',
                'InvitedByFirstName',
                'InvitedByLastName',
                'InvitedByEmail',
                'InvitedByFullName',
            ],
        ],
        'Reset Password' => [
            'editor' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'Link',
                'CompanyName',
            ],
            'allowed' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'Link',
                'CompanyName',
            ],
        ],
        'Password Reset Notification' => [
            'editor' => self::BASE_TOKENS,
            'allowed' => self::BASE_TOKENS,
        ],
        'Verify Email' => [
            'editor' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'Link',
                'CompanyName',
            ],
            'allowed' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'Link',
                'CompanyName',
            ],
        ],
        'Payment Success' => [
            'editor' => self::BASE_TOKENS,
            'allowed' => self::BASE_TOKENS,
        ],
        'Payment Failed' => [
            'editor' => self::BASE_TOKENS,
            'allowed' => self::BASE_TOKENS,
        ],
        'Subscription Renewal' => [
            'editor' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'CompanyName',
                'plan',
            ],
            'allowed' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'CompanyName',
                'plan',
            ],
        ],
        'Subscription Plan Purchase' => [
            'editor' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'CompanyName',
                'plan',
            ],
            'allowed' => [
                'FirstName',
                'LastName',
                'Email',
                'FullName',
                'CompanyName',
                'plan',
            ],
        ],
    ];

    public static function editorPlaceholdersFor(?string $templateName): array
    {
        return array_map(
            static fn (string $token) => self::formatToken($token),
            self::definitionFor($templateName)['editor']
        );
    }

    public static function unsupportedPlaceholdersFor(?string $templateName, ?string $content): array
    {
        $allowedTokens = self::definitionFor($templateName)['allowed'];
        $unsupported = [];

        foreach (self::extractTokens($content) as $token) {
            if (! in_array($token, $allowedTokens, true)) {
                $unsupported[] = self::formatToken($token);
            }
        }

        return array_values(array_unique($unsupported));
    }

    private static function definitionFor(?string $templateName): array
    {
        return self::DEFINITIONS[(string) $templateName] ?? [
            'editor' => self::BASE_TOKENS,
            'allowed' => self::BASE_TOKENS,
        ];
    }

    private static function extractTokens(?string $content): array
    {
        $content = self::decodeContent((string) ($content ?? ''));

        if ($content === '') {
            return [];
        }

        preg_match_all('/\{\{[A-Za-z][A-Za-z0-9]*\}\}|\{[A-Za-z][A-Za-z0-9]*\}/', $content, $matches);

        $tokens = [];
        foreach ($matches[0] ?? [] as $match) {
            $token = self::normalizeToken($match);
            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }

    private static function decodeContent(string $content): string
    {
        $decoded = $content;

        for ($i = 0; $i < 2; $i++) {
            $candidate = rawurldecode($decoded);
            if ($candidate === $decoded) {
                break;
            }

            $decoded = $candidate;
        }

        return $decoded;
    }

    private static function normalizeToken(string $token): ?string
    {
        if (str_starts_with($token, '{{') && str_ends_with($token, '}}')) {
            $token = substr($token, 2, -2);
        } elseif (str_starts_with($token, '{') && str_ends_with($token, '}')) {
            $token = substr($token, 1, -1);
        } else {
            return null;
        }

        return match ($token) {
            'FirstName', 'firstName' => 'FirstName',
            'LastName', 'lastName' => 'LastName',
            'Email', 'email' => 'Email',
            'FullName', 'fullName' => 'FullName',
            'Link', 'link', 'verificationLink', 'resetLink' => 'Link',
            'plan', 'planName', 'plan_name' => 'plan',
            'CompanyName', 'companyName' => 'CompanyName',
            'InvitedByFirstName', 'invitedByFirstName' => 'InvitedByFirstName',
            'InvitedByLastName', 'invitedByLastName' => 'InvitedByLastName',
            'InvitedByEmail', 'invitedByEmail' => 'InvitedByEmail',
            'InvitedByFullName', 'invitedByFullName' => 'InvitedByFullName',
            default => $token,
        };
    }

    private static function formatToken(string $token): string
    {
        return '{{' . $token . '}}';
    }
}
