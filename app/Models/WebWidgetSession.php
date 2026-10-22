<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebWidgetSession extends Model {
    use HasUuid;

    protected $guarded = [];
    protected $hidden = ['token_hash', 'ip_hash'];
    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(WebWidget::class, 'web_widget_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public static function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
