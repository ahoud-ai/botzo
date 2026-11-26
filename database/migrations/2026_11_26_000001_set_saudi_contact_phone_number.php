<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Data only: the public contact and WhatsApp number becomes the Saudi number.
 * A value an admin already changed to something else is left alone.
 */
return new class extends Migration
{
    private const OLD_PHONE = '0557170022';

    private const NEW_PHONE = '+966535200404';

    private const KEYS = [
        'frontend_contact_phone_primary',
        'frontend_contact_phone_secondary',
        'phone',
    ];

    public function up(): void
    {
        foreach (self::KEYS as $key) {
            $setting = Setting::where('key', $key)->first();

            if (! $setting) {
                Setting::create(['key' => $key, 'value' => self::NEW_PHONE]);

                continue;
            }

            if (in_array(trim((string) $setting->value), ['', self::OLD_PHONE], true)) {
                $setting->update(['value' => self::NEW_PHONE]);
            }
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', self::KEYS)
            ->where('value', self::NEW_PHONE)
            ->update(['value' => self::OLD_PHONE]);
    }
};
