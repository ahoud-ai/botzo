<?php

namespace Modules\IntelliReply\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model {
    use HasUuid;

    public const MAX_PER_ORGANIZATION = 50;
    public const MAX_QUESTION_LENGTH = 300;
    public const MAX_ANSWER_LENGTH = 1000;

    protected $table = 'ai_faqs';
    protected $guarded = [];
    protected $hidden = ['embedding'];
    protected $casts = [
        'embedding' => 'array',
    ];
}
