<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\SensitiveSettingCodec;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];
    public $timestamps = false;

    /** Secrets are stored encrypted; callers always get the plain value. */
    public function getValueAttribute($value)
    {
        return SensitiveSettingCodec::decrypt($value);
    }

    public function setValueAttribute($value): void
    {
        $this->attributes['value'] = SensitiveSettingCodec::encodeSetting($this->attributes['key'] ?? null, $value);
    }

    public static function getValueByKey($key)
    {
        $setting = self::where('key', $key)->first();

        return $setting ? $setting->value : null;
    }
}
