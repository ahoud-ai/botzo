<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::created(function ($chat) {
            $contact = $chat->contact;
            if ($contact) {
                // Ensure latest_chat_created_at is updated in UTC timezone
                // Access raw attribute to avoid timezone conversion from accessor
                $rawCreatedAt = $chat->getAttributes()['created_at'] ?? null;
                
                // If created_at is set, parse it and ensure UTC; otherwise use current UTC time
                $utcTimestamp = $rawCreatedAt 
                    ? Carbon::parse($rawCreatedAt)->utc()->format('Y-m-d H:i:s')
                    : Carbon::now('UTC')->format('Y-m-d H:i:s');
                
                $contact->latest_chat_created_at = $utcTimestamp;
                $contact->save();
            }
        });

        /*static::updated(function ($chat) {
            $contact = $chat->contact;
            if ($contact) {
                $latestChat = Chat::where('contact_id', $contact->id)->orderBy('created_at', 'desc')->first();
                $contact->latest_chat_created_at = $latestChat ? $latestChat->created_at : null;
                $contact->save();
            }
        });*/
    }
    
    public function getCreatedAtAttribute($value)
    {
        return DateTimeHelper::convertToOrganizationTimezone($value)->toDateTimeString();
    }
    
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'id');
    }

    public function media()
    {
        return $this->belongsTo(ChatMedia::class, 'media_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(ChatStatusLog::class, 'chat_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
