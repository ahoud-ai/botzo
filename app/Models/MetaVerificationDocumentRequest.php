<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaVerificationDocumentRequest extends Model
{
    protected $guarded = [];
    public $timestamps = true;

    public const STATUS_PENDING = 'pending';
    public const STATUS_FULFILLED = 'fulfilled';

    public function request(): BelongsTo
    {
        return $this->belongsTo(MetaVerificationRequest::class, 'meta_verification_request_id');
    }

    public function requestedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function fulfilledDocument(): BelongsTo
    {
        return $this->belongsTo(MetaVerificationDocument::class, 'fulfilled_document_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
