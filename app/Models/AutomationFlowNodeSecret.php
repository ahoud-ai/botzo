<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationFlowNodeSecret extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = [];

    protected $casts = [
        'payload_json' => 'encrypted:array',
    ];

    public function flow()
    {
        return $this->belongsTo(AutomationFlow::class, 'automation_flow_id');
    }
}
