<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function documents(): HasMany
    {
        return $this->hasMany(MetaVerificationDocument::class);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(MetaVerificationDocumentRequest::class)->latest();
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

    /**
     * Bilingual (Arabic · English) label for a status, matching the wording already
     * shown in MetaVerificationStepper.vue — used for the status-update email, which
     * (like the other seeded EmailTemplate records) is not locale-aware.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_REQUESTED => 'طلب مُقدَّم · Requested',
            self::STATUS_AWAITING_AGREEMENT => 'انتظار الاتفاقية · Awaiting agreement',
            self::STATUS_AWAITING_PAYMENT => 'انتظار السداد · Awaiting payment',
            self::STATUS_UNDER_REVIEW => 'مراجعة داخلية · Under review',
            self::STATUS_SUBMITTED_TO_META => 'تقديم لـ Meta · Submitted to Meta',
            self::STATUS_META_REVIEWING => 'مراجعة Meta · Meta reviewing',
            self::STATUS_APPROVED => 'تم قبول التوثيق · Verification approved',
            self::STATUS_REJECTED => 'تم رفض التوثيق · Verification rejected',
            default => $status,
        };
    }

    /**
     * Short "what happens next" hint per status, shown in the status-update email.
     */
    public static function nextStepNote(string $status): string
    {
        return match ($status) {
            self::STATUS_AWAITING_AGREEMENT => 'الخطوة التالية: راجع اتفاقية الخدمة ووقّعها، وارفع نسخة موقعة منها من لوحة تتبع الطلب. · Next step: review and sign the service agreement, then upload a signed copy from your request tracking dashboard.',
            self::STATUS_AWAITING_PAYMENT => 'الخطوة التالية: إتمام سداد قيمة الخدمة، وارفع إثبات السداد من لوحة تتبع الطلب. · Next step: complete payment for the service, then upload proof of payment from your request tracking dashboard.',
            self::STATUS_UNDER_REVIEW => 'فريقنا بيراجع بيانات نشاطك التجاري قبل التقديم لـ Meta. · Our team is reviewing your business details before submitting to Meta.',
            self::STATUS_SUBMITTED_TO_META => 'تم تقديم طلب التوثيق رسميًا لـ Meta وجاري انتظار الرد. · Your verification request has been submitted to Meta and is awaiting a decision.',
            self::STATUS_META_REVIEWING => 'Meta بتراجع الطلب حاليًا، هنبلغك أول ما يكون فيه قرار. · Meta is currently reviewing your request — we will notify you as soon as a decision is made.',
            self::STATUS_APPROVED => 'مبروك! حساب واتساب بيزنس بتاعك موثّق رسميًا الآن. · Congratulations! Your WhatsApp Business account is now officially verified.',
            default => '',
        };
    }
}
