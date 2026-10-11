<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'audio_seconds' => 'float',
        'cost_usd' => 'float',
    ];
}
