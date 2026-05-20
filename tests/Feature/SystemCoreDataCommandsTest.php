<?php

namespace Tests\Feature;

use App\Contracts\QueueProfileContract;
use App\Models\Addon;
use App\Models\Language;
use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SystemCoreDataCommandsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bootstrap_core_data_fills_minimum_required_rows(): void
    {
        $this->assertFalse(Addon::query()->whereIn('name', [
            'AI Assistant',
            'Flow builder',
        ])->exists());
        $this->assertSame(0, Language::query()->count());

        $exitCode = Artisan::call('system:bootstrap-core-data');
        $this->assertSame(0, $exitCode);

        $this->assertTrue(Addon::query()->where('name', 'AI Assistant')->exists());
        $this->assertTrue(Addon::query()->where('name', 'Flow builder')->exists());
        $this->assertFalse(Addon::query()->whereIn('name', ['Google Recaptcha', 'Google Authenticator'])->exists());
        $addon = Addon::query()->where('name', 'Flow builder')->firstOrFail();
        $metadata = json_decode((string) $addon->metadata, true);
        $this->assertSame('FlowBuilder', $metadata['name'] ?? null);
        $this->assertFalse((bool) ($metadata['decommissioned'] ?? false));
        $this->assertFalse((bool) ($metadata['internal_only'] ?? false));
        $this->assertTrue(Language::query()->where('code', 'en')->exists());
        $this->assertTrue(Language::query()->where('code', 'ar')->exists());
        $this->assertNotNull(Setting::query()->where('key', 'broadcast_driver')->value('value'));
    }

    public function test_health_check_fails_before_bootstrap_and_passes_after_bootstrap(): void
    {
        $exitBefore = Artisan::call('system:health-check');
        $this->assertSame(1, $exitBefore);

        Artisan::call('system:bootstrap-core-data');
        $this->configureReadyRuntimeProfile();

        $exitAfter = Artisan::call('system:health-check');
        $this->assertSame(0, $exitAfter);
    }

    private function configureReadyRuntimeProfile(): void
    {
        config()->set('queue_profile.active', 'shared');
        config()->set('queue_profile.shared.connection', 'sync');
        config()->set('queue_profile.shared.cache_store', 'array');
        config()->set('queue_profile.shared.session_driver', 'array');
        config()->set('queue_profile.shared.workers', [
            [
                'name' => 'test-worker',
                'queues' => [
                    'default',
                    'campaign-messages',
                    'webhook-media',
                ],
                'sleep' => 1,
                'tries' => 1,
                'timeout' => 60,
            ],
        ]);
        config()->set('queue.default', 'sync');
        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        $this->app->forgetInstance(QueueProfileContract::class);
    }
}
