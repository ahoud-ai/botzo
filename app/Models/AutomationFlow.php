<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutomationFlow extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'graph_json' => 'array',
        'ui_json' => 'array',
        'has_unpublished_changes' => 'boolean',
        'last_published_at' => 'datetime',
    ];

    public function currentVersion()
    {
        return $this->belongsTo(AutomationFlowVersion::class, 'current_version_id');
    }

    public function versions()
    {
        return $this->hasMany(AutomationFlowVersion::class);
    }

    public function runs()
    {
        return $this->hasMany(AutomationFlowRun::class);
    }

    public function assets()
    {
        return $this->hasMany(AutomationFlowAsset::class);
    }

    public function nodeSecrets()
    {
        return $this->hasMany(AutomationFlowNodeSecret::class);
    }
}
