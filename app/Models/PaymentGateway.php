<?php

namespace App\Models;

use App\Support\SensitiveSettingCodec;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model {
    use HasFactory;

    protected $guarded = [];
    public $timestamps = true;

    /** Gateway metadata holds API and webhook secrets, so it is stored encrypted. */
    public function getMetadataAttribute($value)
    {
        return SensitiveSettingCodec::decrypt($value);
    }

    public function setMetadataAttribute($value): void
    {
        $this->attributes['metadata'] = SensitiveSettingCodec::encrypt($value);
    }

    public function listAll()
    {
        $query = $this->paginate(10);

        return $query;
    }
}
