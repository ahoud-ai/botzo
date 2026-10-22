<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use App\Services\WebWidget\WebWidgetDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebWidget extends Model {
    use HasUuid;

    protected $guarded = [];
    protected $hidden = ['id', 'organization_id', 'created_by'];
    protected $casts = [
        'is_enabled' => 'boolean',
        'allowed_domains' => 'array',
        'settings' => 'array',
        'daily_reply_limit' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public static function generatePublicKey(): string
    {
        return 'wgt_'.Str::random(40);
    }

    /**
     * The display settings with every missing key filled from the platform defaults
     * (config('web_widget.defaults') with the admin's overrides).
     */
    public function settingsWithDefaults(): array
    {
        $defaults = app(WebWidgetDefaults::class)->settings();

        return array_replace($defaults, array_intersect_key((array) $this->settings, $defaults));
    }

    /**
     * Whether a request from $origin (scheme://host[:port]) may use this widget:
     * its host must be one of the allowed domains, with or without "www.".
     */
    public function allowsOrigin(?string $origin): bool
    {
        $host = parse_url((string) $origin, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = self::withoutWww(strtolower($host));
        foreach ((array) $this->allowed_domains as $domain) {
            if (is_string($domain) && self::withoutWww($domain) === $host) {
                return true;
            }
        }

        return false;
    }

    /**
     * "https://www.Example.com/path" → "www.example.com"; null when it is not a domain name.
     */
    public static function normalizeDomain(string $input): ?string
    {
        $value = strtolower(trim($input));
        if ($value === '') {
            return null;
        }

        $host = parse_url(str_contains($value, '://') ? $value : 'https://'.$value, PHP_URL_HOST);
        if (! is_string($host) || strlen($host) > 253) {
            return null;
        }

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) === 1 ? $host : null;
    }

    private static function withoutWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
