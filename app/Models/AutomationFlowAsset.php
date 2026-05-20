<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationFlowAsset extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = [];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function flow()
    {
        return $this->belongsTo(AutomationFlow::class, 'automation_flow_id');
    }
}
