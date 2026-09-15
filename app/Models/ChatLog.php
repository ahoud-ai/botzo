<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\DateTimeHelper;
use App\Models\Chat;
use App\Models\ChatTicket;
use App\Models\ChatNote;
use Carbon\Carbon;

class ChatLog extends Model {
    use HasFactory;

    protected $guarded = [];
    public $timestamps = false;

    // Accessor to format created_at with organization's timezone
    public function getCreatedAtAttribute($value)
    {
        // Stored in UTC — tag it explicitly before conversion, since a raw
        // naive string would otherwise be parsed using the app's local
        // default timezone instead of being treated as UTC.
        return DateTimeHelper::convertToOrganizationTimezone(Carbon::parse($value, 'UTC'))->toDateTimeString();
    }

    public function getUpdatedAtAttribute($value)
    {
        return DateTimeHelper::convertToOrganizationTimezone(Carbon::parse($value, 'UTC'))->toDateTimeString();
    }

    public function entity()
    {
        return $this->morphTo('entity');
    }

    public function getRelatedEntitiesAttribute()
    {
        $entityType = $this->entity_type;
        $entityId = $this->entity_id;
        $relatedEntity = null;

        switch ($entityType) {
            case 'chat':
                $relatedEntity = Chat::with('media', 'user', 'logs')->find($entityId);
                break;
            case 'ticket':
                $relatedEntity = ChatTicketLog::find($entityId);
                break;
            case 'notes':
                $relatedEntity = ChatNote::find($entityId);
                break;
        }

        return $relatedEntity;
    }
}
