<?php

namespace Tests\Feature;

use App\Models\WebWidget;
use App\Services\AddonStateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WebWidgetSettingsTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private bool $aiModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturnUsing(fn () => $this->aiModule);
    }

    public function test_the_page_shows_defaults_before_the_first_save(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/widget')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('IntelliReply::User/Widget', false)
                ->where('widget.exists', false)
                ->where('widget.settings.accent', '#22D366')
                ->where('widget.settings.theme', 'mochi')
                ->where('widget.settings.assistant_name', 'موتشي')
                ->where('embedCode', null));
    }

    public function test_saving_creates_the_widget_with_clean_settings_and_an_embed_code(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/widget', $this->payload([
                'allowed_domains' => ['https://www.MyStore.com/ar', '', 'mystore.com'],
                'suggestions' => ['الأسعار', '  ', 'مواعيد العمل', ''],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $widget = WebWidget::where('organization_id', $organization->id)->sole();
        $this->assertTrue($widget->is_enabled);
        $this->assertSame(['www.mystore.com', 'mystore.com'], $widget->allowed_domains);
        $this->assertSame(['الأسعار', 'مواعيد العمل'], $widget->settings['suggestions']);
        $this->assertSame('botzo', $widget->settings['theme']);
        $this->assertStringStartsWith('wgt_', $widget->public_key);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/widget')
            ->assertInertia(fn ($page) => $page->where('embedCode', fn ($code) => str_contains($code, $widget->public_key) && str_contains($code, '/widget.js')));
    }

    public function test_the_widget_cannot_be_turned_on_without_a_valid_domain(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/widget', $this->payload(['allowed_domains' => ['']]))
            ->assertSessionHasErrors('allowed_domains');

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/widget', $this->payload(['allowed_domains' => ['not a domain']]))
            ->assertSessionHasErrors('allowed_domains.0');

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/widget', $this->payload(['accent' => 'red']))
            ->assertSessionHasErrors('accent');

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/widget', $this->payload(['theme' => 'neon']))
            ->assertSessionHasErrors('theme');

        $this->assertSame(0, WebWidget::where('organization_id', $organization->id)->count());
    }

    public function test_navigation_pages_must_be_on_the_customers_own_site(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/widget', $this->payload(['pages' => [
                ['label' => 'الأسعار', 'url' => '/pricing'],
                ['label' => 'المميزات', 'url' => '#features'],
                ['label' => 'المدونة', 'url' => 'https://www.mystore.com/blog'],
                ['label' => '', 'url' => ''],
            ]]))
            ->assertSessionHasNoErrors();

        $pages = WebWidget::where('organization_id', $organization->id)->sole()->settings['pages'];
        $this->assertSame(['/pricing', '#features', 'https://www.mystore.com/blog'], array_column($pages, 'url'));

        foreach (['https://evil.example.com/x', 'javascript:alert(1)', '//evil.example.com', 'pricing page'] as $url) {
            $this->actingAs($user, 'user')
                ->withSession(['current_organization' => $organization->id])
                ->post('/automation/widget', $this->payload(['pages' => [['label' => 'X', 'url' => $url]]]))
                ->assertSessionHasErrors('pages.0.url');
        }
    }

    public function test_a_new_code_replaces_the_old_key(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        $widget = WebWidget::create(['organization_id' => $organization->id, 'public_key' => WebWidget::generatePublicKey(), 'allowed_domains' => ['mystore.com']]);
        $oldKey = $widget->public_key;

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/widget/regenerate-key')
            ->assertRedirect();

        $this->assertNotSame($oldKey, $widget->fresh()->public_key);
    }

    public function test_the_page_is_hidden_without_the_ai_module(): void
    {
        $this->aiModule = false;
        [$user, $organization] = $this->createOwnerContext([], true);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/automation/widget')
            ->assertNotFound();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'is_enabled' => true,
            'allowed_domains' => ['mystore.com'],
            'theme' => 'botzo',
            'accent' => '#034737',
            'position' => 'right',
            'assistant_name' => '',
            'welcome_text' => 'أهلاً بيك',
            'suggestions' => ['', '', '', ''],
            'daily_reply_limit' => 300,
        ], $overrides);
    }
}
