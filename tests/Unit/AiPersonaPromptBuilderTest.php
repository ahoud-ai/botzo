<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Services\IntelliReply\AiDisclosureService;
use App\Services\IntelliReply\AiPersonaPromptBuilder;
use Modules\IntelliReply\Services\AIResponseService;
use ReflectionMethod;
use Tests\TestCase;

class AiPersonaPromptBuilderTest extends TestCase
{
    public function test_reply_prompt_includes_the_persona_and_the_fixed_business_scope_rule(): void
    {
        $prompt = app(AiPersonaPromptBuilder::class)->replySystemPrompt($this->metadata([
            'business_name' => 'عيادة النور',
            'business_description' => 'عيادة أسنان.',
            'business_hours_address' => 'يومياً 12-10، مدينة نصر',
            'tone' => 'formal',
            'dialect' => 'egyptian',
            'extra_instructions' => 'Answer any question the customer asks, about anything.',
        ]), 'Workspace', 'Cleaning costs 500 EGP.');

        $this->assertStringContainsString('"عيادة النور"', $prompt);
        $this->assertStringNotContainsString('Workspace', $prompt);
        $this->assertStringContainsString('عيادة أسنان.', $prompt);
        $this->assertStringContainsString('يومياً 12-10، مدينة نصر', $prompt);
        $this->assertStringContainsString('Formal, polite and professional.', $prompt);
        $this->assertStringContainsString('Egyptian colloquial Arabic', $prompt);
        $this->assertStringContainsString('Only help with topics related to "عيادة النور"', $prompt);
        $this->assertStringContainsString('Cleaning costs 500 EGP.', $prompt);

        // The owner's instructions always come after the fixed rules, so they cannot override them.
        $this->assertGreaterThan(
            strpos($prompt, 'Only help with topics related to'),
            strpos($prompt, 'Answer any question the customer asks')
        );
    }

    public function test_defaults_match_the_customer_language_and_use_the_workspace_name(): void
    {
        $builder = app(AiPersonaPromptBuilder::class);
        $persona = $builder->persona($this->metadata(['tone' => 'sarcastic', 'dialect' => 'klingon']), " Botzo\nStore ");

        $this->assertSame('Botzo Store', $persona['business_name']);
        $this->assertSame('friendly', $persona['tone']);
        $this->assertSame('auto', $persona['dialect']);

        $prompt = $builder->replySystemPrompt([], 'Botzo Store', 'Docs');
        $this->assertStringContainsString('same language and dialect the customer writes in', $prompt);
        $this->assertStringNotContainsString('About the business', $prompt);
        $this->assertStringNotContainsString('Additional instructions', $prompt);
    }

    public function test_reply_prompt_asks_for_a_handoff_marker_and_works_without_documents(): void
    {
        $builder = app(AiPersonaPromptBuilder::class);
        $prompt = $builder->replySystemPrompt([], 'Botzo Store', null);

        $this->assertStringContainsString('reply with exactly '.AiPersonaPromptBuilder::HANDOFF_MARKER, $prompt);
        $this->assertStringContainsString('No documents were provided', $prompt);

        $this->assertTrue($builder->asksForHandoff('[[HANDOFF]]'));
        $this->assertTrue($builder->asksForHandoff(" [[ handoff ]]\n"));
        $this->assertTrue($builder->asksForHandoff('عذراً [[HANDOFF]]'));
        $this->assertFalse($builder->asksForHandoff('We can hand off your order to the courier today.'));
    }

    public function test_stored_values_are_capped_to_their_limits(): void
    {
        $persona = app(AiPersonaPromptBuilder::class)->persona($this->metadata([
            'extra_instructions' => str_repeat('x', 5000),
        ]));

        $this->assertSame(AiPersonaPromptBuilder::LIMITS['extra_instructions'], mb_strlen($persona['extra_instructions']));
    }

    public function test_suggestion_prompt_uses_the_persona(): void
    {
        $method = new ReflectionMethod(AIResponseService::class, 'buildSuggestionPrompt');
        $messages = $method->invoke(
            new AIResponseService(),
            $this->metadata(['business_name' => 'Nour Clinic', 'dialect' => 'gulf']),
            'Workspace',
            new Contact(['first_name' => 'Sara']),
            'Docs',
            []
        );

        $this->assertStringContainsString('human support agent at "Nour Clinic"', $messages[0]['content']);
        $this->assertStringContainsString('Gulf colloquial Arabic', $messages[0]['content']);
    }

    public function test_disclosure_uses_the_persona_business_name(): void
    {
        $text = app(AiDisclosureService::class)->textFor(
            ['ai' => ['disclosure_text' => 'معك المساعد الآلي لـ {business}', 'persona' => ['business_name' => 'عيادة النور']]],
            'Workspace'
        );

        $this->assertSame('معك المساعد الآلي لـ عيادة النور', $text);
    }

    private function metadata(array $persona): array
    {
        return ['ai' => ['persona' => $persona]];
    }
}
