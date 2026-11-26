<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SaudiContactPhoneMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_old_number_becomes_the_saudi_number_and_custom_values_stay(): void
    {
        Setting::updateOrCreate(['key' => 'phone'], ['value' => '0557170022']);
        Setting::updateOrCreate(['key' => 'frontend_contact_phone_primary'], ['value' => '0557170022']);
        Setting::updateOrCreate(['key' => 'frontend_contact_phone_secondary'], ['value' => '+20 100 000 0000']);

        $migration = require database_path('migrations/2026_11_26_000001_set_saudi_contact_phone_number.php');
        $migration->up();

        $this->assertSame('+966535200404', Setting::where('key', 'phone')->value('value'));
        $this->assertSame('+966535200404', Setting::where('key', 'frontend_contact_phone_primary')->value('value'));
        $this->assertSame('+20 100 000 0000', Setting::where('key', 'frontend_contact_phone_secondary')->value('value'));

        $migration->down();

        $this->assertSame('0557170022', Setting::where('key', 'phone')->value('value'));
    }
}
