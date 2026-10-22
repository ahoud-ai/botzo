<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class OrganizationApiKey extends Model {
    use HasFactory;
    use HasUuid;

    public const SCOPE_FULL = 'full';
    public const SCOPE_SEND = 'send';
    public const SCOPE_READ = 'read';
    // Mochi API only, from a server.
    public const SCOPE_ASSISTANT = 'assistant';
    // Mochi API only, safe to ship inside a mobile app (pk_ prefix, per-device conversation tokens).
    public const SCOPE_PUBLIC = 'public';
    public const SCOPES = [self::SCOPE_FULL, self::SCOPE_SEND, self::SCOPE_READ, self::SCOPE_ASSISTANT, self::SCOPE_PUBLIC];

    protected $guarded = [];
    public $timestamps = true;
    protected $hidden = [
        'id',
        'token',
        'organization_id',
        'deleted_by',
    ];
    protected $casts = [
        'last_used_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Whether this key may call the requested endpoint: "send" keys only send messages and
     * check their status, "read" keys only read, "assistant" and "public" keys only talk to Mochi,
     * "full" keys (every key made before scopes) do everything.
     */
    public function allows(Request $request): bool
    {
        return match ($this->scope ?: self::SCOPE_FULL) {
            self::SCOPE_SEND => $request->is('api/send', 'api/send/*', 'api/messages/*', 'api/templates', 'api/verify'),
            self::SCOPE_READ => $request->isMethodSafe() && ! $request->is('api/v1/assistant/*'),
            self::SCOPE_ASSISTANT => $request->is('api/v1/assistant/*', 'api/verify'),
            self::SCOPE_PUBLIC => $request->is('api/v1/assistant/*'),
            default => true,
        };
    }

    public function isPublic(): bool
    {
        return $this->scope === self::SCOPE_PUBLIC;
    }
}
