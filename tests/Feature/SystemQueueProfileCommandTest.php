<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SystemQueueProfileCommandTest extends TestCase
{
    public function test_queue_profile_command_returns_success_and_expected_values(): void
    {
        $exitCode = Artisan::call('system:queue-profile', ['--format' => 'json']);

        $this->assertSame(0, $exitCode);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertSame('production', $payload['profile']);
        $this->assertSame('redis', $payload['connection']);
        $this->assertSame(
            ['default', 'automation-flow-resume', 'campaign-messages', 'webhook-media'],
            $payload['queues']
        );
    }
}
