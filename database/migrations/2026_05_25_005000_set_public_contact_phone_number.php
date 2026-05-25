<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const CONTACT_PHONE = '0557170022';

    public function up(): void
    {
        foreach ([
            'frontend_contact_phone_primary',
            'frontend_contact_phone_secondary',
            'phone',
        ] as $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => self::CONTACT_PHONE]
            );
        }
    }

    public function down(): void
    {
        foreach ([
            'frontend_contact_phone_primary',
            'frontend_contact_phone_secondary',
            'phone',
        ] as $key) {
            Setting::where('key', $key)
                ->where('value', self::CONTACT_PHONE)
                ->update(['value' => null]);
        }
    }
};
