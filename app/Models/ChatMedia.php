<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMedia extends Model {
    use HasFactory;

    /** Received from a customer: stored outside the public folder and served only to signed-in team members. */
    public const VISIBILITY_PRIVATE = 'private';

    /** Disk folder for private files received from customers. */
    public const PRIVATE_FOLDER = 'private/received';

    protected $guarded = [];
    protected $table = 'chat_media';
    public $timestamps = false;

    // The disk key is an internal detail; the UI uses path (the authenticated URL for private files).
    protected $hidden = ['storage_path'];

    public function getCreatedAtAttribute($value)
    {
        return DateTimeHelper::convertToOrganizationTimezone($value)->toDateTimeString();
    }

    public function isPrivate(): bool
    {
        return $this->visibility === self::VISIBILITY_PRIVATE && is_string($this->storage_path) && $this->storage_path !== '';
    }

    public static function privateUrl(int $id): string
    {
        return rtrim((string) config('app.url'), '/').'/chat-media/'.$id;
    }
}
