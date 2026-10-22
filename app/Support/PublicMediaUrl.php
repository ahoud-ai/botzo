<?php

namespace App\Support;

/**
 * Media sent through the developer API must be a public https link: Meta downloads it, and the
 * server never reads local files or internal addresses because of a URL a caller typed.
 */
class PublicMediaUrl
{
    public static function isAllowed(mixed $url): bool
    {
        if (! is_string($url) || strlen($url) > 2000 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if (strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        // An address typed as an IP must be a public one.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return str_contains($host, '.');
    }

    // Content type from the file extension, so the server does not have to fetch the link to find it.
    public static function contentType(string $url, string $mediaType): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $known = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
            'mp4' => 'video/mp4', '3gp' => 'video/3gpp',
            'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'opus' => 'audio/ogg', 'aac' => 'audio/aac', 'm4a' => 'audio/mp4', 'amr' => 'audio/amr',
            'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain', 'csv' => 'text/csv',
        ];

        return $known[$extension] ?? match ($mediaType) {
            'image' => 'image/jpeg',
            'video' => 'video/mp4',
            'audio' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }
}
