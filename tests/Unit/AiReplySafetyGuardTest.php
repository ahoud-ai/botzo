<?php

namespace Tests\Unit;

use App\Services\IntelliReply\AiReplySafetyGuard;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiReplySafetyGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'intellireply.safety.max_output_tokens' => 1024,
            'intellireply.safety.max_replies_per_contact_per_hour' => 3,
        ]);
    }

    public function test_output_tokens_are_capped_even_when_a_higher_value_is_stored(): void
    {
        $guard = new AiReplySafetyGuard();

        $this->assertSame(1024, $guard->maxOutputTokens(8000));
        $this->assertSame(512, $guard->maxOutputTokens(512));
        $this->assertSame(1024, $guard->maxOutputTokens(0));
    }

    public function test_replies_to_one_contact_stop_after_the_hourly_limit(): void
    {
        $guard = new AiReplySafetyGuard();

        foreach (range(1, 3) as $reply) {
            $this->assertTrue($guard->allowsReplyToContact(10, 20), "Reply {$reply} should be allowed.");
            $guard->recordReplyToContact(10, 20);
        }

        $this->assertFalse($guard->allowsReplyToContact(10, 20));
    }

    public function test_the_hourly_limit_is_tracked_per_contact_and_per_organization(): void
    {
        $guard = new AiReplySafetyGuard();

        foreach (range(1, 3) as $reply) {
            $guard->recordReplyToContact(10, 20);
        }

        $this->assertFalse($guard->allowsReplyToContact(10, 20));
        $this->assertTrue($guard->allowsReplyToContact(10, 21));
        $this->assertTrue($guard->allowsReplyToContact(11, 20));
    }

    public function test_a_zero_limit_disables_the_hourly_cap(): void
    {
        config(['intellireply.safety.max_replies_per_contact_per_hour' => 0]);
        $guard = new AiReplySafetyGuard();

        foreach (range(1, 10) as $reply) {
            $guard->recordReplyToContact(10, 20);
        }

        $this->assertTrue($guard->allowsReplyToContact(10, 20));
    }
}
