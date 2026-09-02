<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaVerificationRequest extends Model
{
    use HasFactory;

    protected $guarded = [];
    public $timestamps = true;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_AWAITING_AGREEMENT = 'awaiting_agreement';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_SUBMITTED_TO_META = 'submitted_to_meta';
    public const STATUS_META_REVIEWING = 'meta_reviewing';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const SEQUENCE = [
        self::STATUS_REQUESTED,
        self::STATUS_AWAITING_AGREEMENT,
        self::STATUS_AWAITING_PAYMENT,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_SUBMITTED_TO_META,
        self::STATUS_META_REVIEWING,
        self::STATUS_APPROVED,
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function nextStatus(): ?string
    {
        $index = array_search($this->status, self::SEQUENCE, true);

        if ($index === false || ! isset(self::SEQUENCE[$index + 1])) {
            return null;
        }

        return self::SEQUENCE[$index + 1];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true);
    }
}
