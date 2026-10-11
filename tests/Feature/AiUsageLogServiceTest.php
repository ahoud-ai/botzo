<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Services\IntelliReply\AiUsageLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Modules\IntelliReply\Services\AIResponseService;
use ReflectionMethod;
use Tests\TestCase;

class AiUsageLogServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_totals_are_grouped_per_model_and_key_source_within_the_period(): void
    {
        $service = app(AiUsageLogService::class);
        $usage = fn (int $in, int $out) => ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'input_tokens' => $in, 'output_tokens' => $out];

        $service->record(900001, 1, AiUsageLogService::PURPOSE_AUTO_REPLY, 'replied', $usage(100, 10), 'global');
        $service->record(900001, 2, AiUsageLogService::PURPOSE_SUGGESTION, 'drafted', $usage(200, 20), 'global');
        $service->record(900001, 3, AiUsageLogService::PURPOSE_AUTO_REPLY, 'replied', $usage(50, 5), 'organization');
        $service->record(900002, 4, AiUsageLogService::PURPOSE_AUTO_REPLY, 'replied', $usage(999, 99), 'global');
        AiUsageLog::create(['organization_id' => 900001, 'purpose' => 'auto_reply', 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'key_source' => 'global', 'input_tokens' => 7000, 'created_at' => now()->subMonths(2)]);

        $totals = collect($service->totals(900001, now()->subDay(), now()->addMinute()))->keyBy('key_source');

        $this->assertSame(['requests' => 2, 'input_tokens' => 300, 'output_tokens' => 30], collect($totals['global'])->only(['requests', 'input_tokens', 'output_tokens'])->all());
        $this->assertSame(['requests' => 1, 'input_tokens' => 50, 'output_tokens' => 5], collect($totals['organization'])->only(['requests', 'input_tokens', 'output_tokens'])->all());
    }

    public function test_agent_suggestions_are_logged(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [['message' => ['content' => 'Draft']]],
            'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 12],
        ])]);

        $method = new ReflectionMethod(AIResponseService::class, 'requestTextCompletion');
        $text = $method->invoke(new AIResponseService(), 900004, ['ai' => ['model' => 'gpt-4o-mini']], [['role' => 'user', 'content' => 'hi']], 'sk-test', ['source' => 'global'], 55);

        $this->assertSame('Draft', $text);
        $this->assertDatabaseHas('ai_usage_logs', [
            'organization_id' => 900004,
            'contact_id' => 55,
            'purpose' => 'suggestion',
            'outcome' => 'drafted',
            'key_source' => 'global',
            'input_tokens' => 300,
            'output_tokens' => 12,
        ]);
    }

    public function test_missing_usage_fields_are_stored_as_zero_and_unknown(): void
    {
        app(AiUsageLogService::class)->record(900003, null, AiUsageLogService::PURPOSE_AUTO_REPLY, 'replied', [], null);

        $this->assertDatabaseHas('ai_usage_logs', [
            'organization_id' => 900003,
            'provider' => 'unknown',
            'model' => 'unknown',
            'input_tokens' => 0,
            'output_tokens' => 0,
        ]);
    }
}
