<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationFlowRun extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = [];

    protected $casts = [
        'state_json' => 'array',
        'last_input_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_resume_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function flow()
    {
        return $this->belongsTo(AutomationFlow::class, 'automation_flow_id');
    }

    public function version()
    {
        return $this->belongsTo(AutomationFlowVersion::class, 'automation_flow_version_id');
    }

    public function steps()
    {
        return $this->hasMany(AutomationFlowRunStep::class);
    }
}
