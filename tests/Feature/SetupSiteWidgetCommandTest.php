<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WebWidget;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\IntelliReply\Models\Faq;
use Tests\TestCase;

class SetupSiteWidgetCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_sets_up_the_botzo_organization_and_is_safe_to_run_again(): void
    {
        $email = 'site+'.Str::lower(Str::random(6)).'@example.com';
        $top = SubscriptionPlan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Top '.Str::random(4),
            'price' => 99999,
            'period' => 'monthly',
            'metadata' => json_encode(['tier_rank' => 999, 'addons' => ['AI Assistant' => true]]),
            'status' => 'active',
        ]);

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
    }

    public function test_an_invalid_email_is_refused(): void
    {
        $this->artisan('botzo:setup-site-widget', ['email' => 'not-an-email'])->assertFailed();
    }
}
