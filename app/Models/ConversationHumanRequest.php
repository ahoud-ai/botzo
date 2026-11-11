<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A conversation waiting for the team. Only explicit requests for a person keep the assistant
 * from coming back; the others just tell the team that a customer is waiting.
 */
class ConversationHumanRequest extends Model
{
    use HasUuid;

    public const REASON_CUSTOMER_REQUEST = 'customer_request';
    public const REASON_VOICE_REQUEST = 'voice_request';
    public const REASON_FLOW_HANDOFF = 'flow_handoff';
    public const REASON_KNOWLEDGE_GAP = 'knowledge_gap';
    public const REASON_AI_UNAVAILABLE = 'ai_unavailable';
    public const REASON_ASSISTANT_OFF = 'assistant_off';

    public const BLOCKING_REASONS = [
        self::REASON_CUSTOMER_REQUEST,
        self::REASON_VOICE_REQUEST,
        self::REASON_FLOW_HANDOFF,
    ];

    public const RESOLUTION_TEAM_REPLY = 'team_reply';
    public const RESOLUTION_MANUAL_CLOSE = 'manual_close';
    public const RESOLUTION_ASSISTANT_RESUMED = 'assistant_resumed';

    protected $guarded = [];

    protected $casts = [
        'blocks_assistant' => 'boolean',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
