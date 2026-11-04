<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SallaWebhookEvent extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'merchant_id' => 'integer',
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
