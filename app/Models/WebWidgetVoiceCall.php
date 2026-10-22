<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebWidgetVoiceCall extends Model {
    use HasUuid;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ENDED = 'ended';

    protected $guarded = [];
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'max_seconds' => 'integer',
        'seconds' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WebWidgetSession::class, 'web_widget_session_id');
    }

    /**
     * Seconds that count against the limits: the recorded length once ended, otherwise the time
     * since the start (a call left open is counted up to its maximum).
     */
    public function countedSeconds(): int
    {
        if ($this->status === self::STATUS_ENDED) {
            return (int) $this->seconds;
        }

        return (int) min($this->max_seconds, max(0, $this->started_at->diffInSeconds(now(), true)));
    }
}
