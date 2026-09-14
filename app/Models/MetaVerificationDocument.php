<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaVerificationDocument extends Model
{
    protected $guarded = [];
    public $timestamps = true;

    public const TYPE_COMMERCIAL_REGISTER = 'commercial_register';
    public const TYPE_ID_PROOF = 'id_proof';
    public const TYPE_OWNERSHIP_PROOF = 'ownership_proof';
    public const TYPE_ADDITIONAL = 'additional';

    /**
     * The document types collected on the initial request form — one clearly
     * labeled upload field per type, matched to the form field name used for it.
     */
    public const INITIAL_DOCUMENT_TYPES = [
        self::TYPE_COMMERCIAL_REGISTER => [
            'field' => 'commercial_register_document',
            'label' => 'مستند السجل التجاري · Commercial register document',
        ],
        self::TYPE_ID_PROOF => [
            'field' => 'id_proof_document',
            'label' => "إثبات هوية المالك · Owner's ID proof",
        ],
        self::TYPE_OWNERSHIP_PROOF => [
            'field' => 'ownership_proof_document',
            'label' => 'إثبات ملكية النشاط · Proof of business ownership',
        ],
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MetaVerificationRequest::class, 'meta_verification_request_id');
    }
}
