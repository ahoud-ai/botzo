<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    // Minutes to wait before each retry; the first attempt is immediate.
    public const RETRY_MINUTES = [1, 5, 30, 120];

    protected $guarded = [];
    protected $casts = [
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id')->withTrashed();
    }
}
