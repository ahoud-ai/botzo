<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class WebhookEndpoint extends Model
{
    use HasUuid;
    use SoftDeletes;

    public const EVENT_MESSAGE_RECEIVED = 'message.received';
    public const EVENT_MESSAGE_STATUS = 'message.status';
    public const EVENT_CONVERSATION_HANDOFF = 'conversation.handoff';
    public const EVENTS = [self::EVENT_MESSAGE_RECEIVED, self::EVENT_MESSAGE_STATUS, self::EVENT_CONVERSATION_HANDOFF];

    public const MAX_PER_ORGANIZATION = 3;

    // After this many failed attempts in a row the endpoint is switched off.
    public const DISABLE_AFTER_FAILURES = 20;

    protected $guarded = [];
    protected $hidden = ['id', 'organization_id', 'secret_encrypted', 'created_by', 'deleted_at'];
    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'disabled_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    public function secret(): string
    {
        return Crypt::decryptString($this->secret_encrypted);
    }

    public function listensTo(string $event): bool
    {
        return in_array($event, (array) $this->events, true);
    }
}
