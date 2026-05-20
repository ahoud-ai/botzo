<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationFlowVersion extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = [];

    protected $casts = [
        'graph_json' => 'array',
        'ui_json' => 'array',
        'compiled_json' => 'array',
        'published_at' => 'datetime',
    ];

    public function flow()
    {
        return $this->belongsTo(AutomationFlow::class, 'automation_flow_id');
    }
}
