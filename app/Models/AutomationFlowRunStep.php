<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationFlowRunStep extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'input_json' => 'array',
        'output_json' => 'array',
        'metadata_json' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(AutomationFlowRun::class, 'automation_flow_run_id');
    }
}
