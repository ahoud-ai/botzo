<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A merchant's store on Salla and the Botzo organization created for it.
 */
class SallaStore extends Model
{
    public const STATUS_INSTALLED = 'installed';

    public const STATUS_UNINSTALLED = 'uninstalled';

    /** Salla refused the refresh token. It must not be retried; only a new authorize event recovers. */
    public const STATUS_REAUTH_REQUIRED = 'reauth_required';

    protected $guarded = [];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'merchant_id' => 'integer',
        'organization_id' => 'integer',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
        'purge_after' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public static function lockKey(int $merchantId): string
    {
        return 'salla:store:'.$merchantId;
    }
}
