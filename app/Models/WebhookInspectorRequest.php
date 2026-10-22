<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookInspectorRequest extends Model
{
    public $timestamps = false;

    protected $guarded = [];
    protected $casts = [
        'headers' => 'array',
        'signature_valid' => 'boolean',
        'created_at' => 'datetime',
    ];
}
