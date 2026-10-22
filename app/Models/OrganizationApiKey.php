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
    public const SCOPES = [self::SCOPE_FULL, self::SCOPE_SEND, self::SCOPE_READ];

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
     * check their status, "read" keys only read, "full" keys (every key made before scopes) do everything.
     */
    public function allows(Request $request): bool
    {
        return match ($this->scope ?: self::SCOPE_FULL) {
            self::SCOPE_SEND => $request->is('api/send', 'api/send/*', 'api/messages/*', 'api/templates', 'api/verify'),
            self::SCOPE_READ => $request->isMethodSafe(),
            default => true,
        };
    }
}
