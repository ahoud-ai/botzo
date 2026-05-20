<?php

namespace Tests\Unit;

use App\Contracts\QueueProfileContract;
use Tests\TestCase;

class QueueProfileServiceTest extends TestCase
{
    public function test_queue_profile_defaults_are_scale_ready(): void
    {
        $profile = app(QueueProfileContract::class);

        $this->assertSame('production', $profile->getName());
        $this->assertSame('redis', $profile->getConnection());
        $this->assertSame('redis', $profile->getCacheStore());
        $this->assertSame('redis', $profile->getSessionDriver());
        $this->assertSame(
            ['default', 'automation-flow-resume', 'campaign-messages', 'webhook-media'],
            $profile->getQueues()
        );
        $this->assertSame(
            'default,automation-flow-resume,campaign-messages,webhook-media',
            $profile->getQueueList()
        );
        $this->assertCount(3, $profile->getWorkers());
    }

    public function test_shared_profile_can_be_selected_for_shared_hosting(): void
    {
        config()->set('queue_profile.active', 'shared');
        app()->forgetInstance(QueueProfileContract::class);

        $profile = app(QueueProfileContract::class);

        $this->assertSame('shared', $profile->getName());
        $this->assertSame('database', $profile->getConnection());
        $this->assertSame('file', $profile->getCacheStore());
        $this->assertSame('file', $profile->getSessionDriver());
        $this->assertSame(
            ['default', 'automation-flow-resume', 'campaign-messages', 'webhook-media'],
            $profile->getQueues()
        );
        $this->assertCount(1, $profile->getWorkers());
    }
}
