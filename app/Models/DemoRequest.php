<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    use HasFactory;

    public const STATUSES = ['new', 'contacted', 'done'];

    public const SOURCE_WEBSITE = 'website';

    public const SOURCE_MOCHI = 'mochi';

    public const PREFERRED_TIMES = ['morning', 'evening', 'any'];

    protected $guarded = [];
    public $timestamps = true;

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
