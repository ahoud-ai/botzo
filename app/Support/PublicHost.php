<?php

namespace App\Support;

/**
 * Before the server calls a customer's URL (webhooks), its host must resolve only to public
 * addresses, so a URL cannot point the server at itself or at the hosting network.
 */
class PublicHost
{
    /** Replaced in tests; returns the IPv4 addresses of a host. */
    public static ?\Closure $resolver = null;

    public static function urlIsSafe(string $url): bool
    {
        if (! PublicMediaUrl::isAllowed($url)) {
            return false;
        }

        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true; // A literal IP was already checked by PublicMediaUrl.
        }

        $addresses = (self::$resolver ?? fn (string $name) => gethostbynamel($name) ?: [])($host);
        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
