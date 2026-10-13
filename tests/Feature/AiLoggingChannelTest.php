<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Modules\IntelliReply\Services\AIResponseService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AiLoggingChannelTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_the_ai_log_has_its_own_daily_channel_at_info_level(): void
    {
        $channel = config('logging.channels.intellireply');

        $this->assertSame('daily', $channel['driver']);
        $this->assertSame(storage_path('logs/intellireply.log'), $channel['path']);
        $this->assertSame('info', $channel['level']);
    }

    public function test_every_intellireply_log_line_goes_to_the_ai_channel(): void
    {
        $offenders = [];

        foreach (['app', 'modules'] as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file->getExtension() === 'php'
                    && preg_match("/Log::(debug|info|notice|warning|error|critical)\\(\\s*'intellireply\\./", (string) file_get_contents($file->getPathname()))) {
                    $offenders[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'intellireply.* lines must use Log::channel(\'intellireply\') so they are not hidden by LOG_LEVEL.');
    }

    public function test_a_provider_failure_such_as_no_credits_is_written_to_the_ai_log(): void
    {
        $path = storage_path('logs/intellireply-test-'.uniqid().'.log');
        config(['logging.channels.intellireply' => ['driver' => 'single', 'path' => $path, 'level' => 'info']]);
        [, $organization] = $this->createOwnerContext(['ai' => ['model' => 'gpt-4o-mini']], true);
        Http::fake(['api.openai.com/*' => Http::response([
            'error' => ['code' => 'credit_balance_exhausted', 'message' => 'You have no credits remaining.'],
        ], 429)]);

        try {
            $this->assertNull((new AIResponseService())->chat($organization->id, 'text', [['role' => 'user', 'content' => 'hi']], ['key' => 'sk-test', 'source' => 'global']));

            $log = (string) @file_get_contents($path);
            $this->assertStringContainsString('intellireply.chat.request_failed', $log);
            $this->assertStringContainsString('credit_balance_exhausted', $log);
        } finally {
            @unlink($path);
        }
    }
}
