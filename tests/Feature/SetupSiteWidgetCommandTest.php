<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WebWidget;
use App\Services\WebWidget\SiteKnowledgeSync;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\IntelliReply\Models\Document;
use Modules\IntelliReply\Models\Faq;
use Tests\TestCase;

class SetupSiteWidgetCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_sets_up_the_botzo_organization_and_is_safe_to_run_again(): void
    {
        $email = 'site+'.Str::lower(Str::random(6)).'@example.com';
        $top = $this->topPlan();

        $this->artisan('botzo:setup-site-widget', ['email' => $email])
            ->expectsOutputToContain('Body scripts')
            ->assertSuccessful();

        $user = User::where('email', $email)->sole();
        $organization = Organization::where('created_by', $user->id)->where('name', 'Botzo')->sole();
        $metadata = json_decode($organization->metadata, true);
        $subscription = Subscription::where('organization_id', $organization->id)->sole();
        $widget = WebWidget::where('organization_id', $organization->id)->sole();

        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($metadata['ai']['active']);
        $this->assertSame('gulf', $metadata['ai']['persona']['dialect']);
        $this->assertSame('global', $metadata['ai']['key_source']);
        $this->assertSame($top->id, (int) $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue(now()->addMonths(11)->lt($subscription->valid_until));
        $this->assertTrue($widget->is_enabled);
        $this->assertSame(['botzo.net'], $widget->allowed_domains);
        $faqCount = Faq::where('organization_id', $organization->id)->count();
        $this->assertGreaterThan(10, $faqCount);

        // A second run adds nothing and keeps the same widget key.
        $this->artisan('botzo:setup-site-widget', ['email' => $email])->assertSuccessful();

        $this->assertSame(1, Organization::where('created_by', $user->id)->where('name', 'Botzo')->count());
        $this->assertSame($faqCount, Faq::where('organization_id', $organization->id)->count());
        $this->assertSame($widget->public_key, WebWidget::where('organization_id', $organization->id)->sole()->public_key);

        // --update-widget re-applies the look and texts but keeps the key and domains.
        $widget->update(['settings' => ['theme' => 'classic', 'welcome_text' => 'old'], 'allowed_domains' => ['botzo.net', 'www.botzo.net']]);
        $this->artisan('botzo:setup-site-widget', ['email' => $email, '--update-widget' => true])->assertSuccessful();
        $widget->refresh();
        $this->assertSame('botzo', $widget->settings['theme']);
        $this->assertSame('مساعد بوتزو الذكي', $widget->settings['assistant_name']);
        $this->assertSame(['botzo.net', 'www.botzo.net'], $widget->allowed_domains);
        $this->assertSame($widget->public_key, WebWidget::where('organization_id', $organization->id)->sole()->public_key);
    }

    public function test_knowledge_option_loads_documents_and_refreshes_the_assistant_content(): void
    {
        config(['logging.channels.intellireply' => ['driver' => 'null']]);
        Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => [['embedding' => [0.1, 0.2]]]])]);
        $email = 'site+'.Str::lower(Str::random(6)).'@example.com';
        $this->topPlan();

        $this->artisan('botzo:setup-site-widget', ['email' => $email])->assertSuccessful();
        $organization = Organization::where('created_by', User::where('email', $email)->sole()->id)->where('name', 'Botzo')->sole();

        // Simulate older content: a stale FAQ answer and an edited persona.
        Faq::where('organization_id', $organization->id)->where('question', 'كم الأسعار؟')->update(['answer' => 'old']);
        $metadata = json_decode($organization->metadata, true);
        $metadata['ai']['persona']['extra_instructions'] = 'old instructions';
        $metadata['ai']['key_source'] = 'organization';
        $metadata['ai']['api_key_encrypted'] = Crypt::encryptString('sk-test');
        $organization->update(['metadata' => json_encode($metadata)]);

        $this->artisan('botzo:setup-site-widget', ['email' => $email, '--knowledge' => true])
            ->expectsOutputToContain('Knowledge documents:')
            ->assertSuccessful();

        $metadata = json_decode($organization->fresh()->metadata, true);
        $this->assertStringContainsString('الباقات والأسعار', $metadata['ai']['persona']['extra_instructions']);
        $this->assertStringContainsString('3 باقات', Faq::where('organization_id', $organization->id)->where('question', 'كم الأسعار؟')->value('answer'));
        $this->assertTrue(Document::where('organization_id', $organization->id)->where('title', SiteKnowledgeSync::PRICING_TITLE)->exists());
        $this->assertGreaterThanOrEqual(10, Document::where('organization_id', $organization->id)->count());
    }

    public function test_an_invalid_email_is_refused(): void
    {
        $this->artisan('botzo:setup-site-widget', ['email' => 'not-an-email'])->assertFailed();
    }

    private function topPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Top '.Str::random(4),
            'price' => 99999,
            'period' => 'monthly',
            'metadata' => json_encode(['tier_rank' => 999, 'addons' => ['AI Assistant' => true]]),
            'status' => 'active',
        ]);
    }
}
