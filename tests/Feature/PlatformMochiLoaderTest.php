<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\User;
use App\Models\WebWidget;
use App\Services\WebWidget\SiteWidget;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class PlatformMochiLoaderTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_customer_pages_load_the_site_widget(): void
    {
        $widget = $this->siteWidget();
        [$user, $organization] = $this->createOwnerContext();

        $this->openDashboard($user, $organization)
            ->assertOk()
            ->assertSee('data-key="'.$widget->public_key.'"', false);
    }

    public function test_customer_pages_do_not_load_it_when_the_site_widget_is_off(): void
    {
        $widget = $this->siteWidget(false);
        [$user, $organization] = $this->createOwnerContext();

        $this->openDashboard($user, $organization)
            ->assertOk()
            ->assertDontSee($widget->public_key, false);
    }

    public function test_the_login_and_admin_pages_still_do_not_load_it(): void
    {
        $widget = $this->siteWidget();

        $this->get('/login')->assertOk()->assertDontSee('data-key="'.$widget->public_key.'"', false);

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings/features/web-widget')
            ->assertOk()
            ->assertDontSee('data-key="'.$widget->public_key.'"', false);
    }

    public function test_public_pages_still_load_it(): void
    {
        $widget = $this->siteWidget();

        $this->get('/')->assertSee('data-key="'.$widget->public_key.'"', false);
    }

    public function test_our_pages_ask_for_the_loader_by_its_content_so_an_old_browser_copy_is_skipped(): void
    {
        $this->siteWidget();
        $version = substr(md5_file(public_path('widget.js')), 0, 10);

        $this->get('/')->assertSee('/widget.js?v='.$version.'"', false);
    }

    public function test_pages_without_the_widget_still_say_how_to_load_it_for_in_app_visits(): void
    {
        $widget = $this->siteWidget();
        $version = substr(md5_file(public_path('widget.js')), 0, 10);

        $this->get('/login')
            ->assertOk()
            ->assertSee('name="botzo-site-widget" content="'.$widget->public_key.'"', false)
            ->assertSee('/widget.js?v='.$version, false);
    }

    public function test_nothing_says_how_to_load_it_when_the_site_widget_is_off(): void
    {
        $this->siteWidget(false);

        $this->get('/login')->assertOk()->assertDontSee('botzo-site-widget', false);
    }

    private function openDashboard($user, $organization)
    {
        return $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/dashboard');
    }

    private function siteWidget(bool $enabled = true): WebWidget
    {
        [, $organization] = $this->createOwnerContext();

        $widget = WebWidget::create([
            'organization_id' => $organization->id,
            'public_key' => WebWidget::generatePublicKey(),
            'is_enabled' => $enabled,
            'allowed_domains' => ['botzo.net'],
            'settings' => ['theme' => 'mochi'],
        ]);

        app(SiteWidget::class)->remember($organization);

        return $widget;
    }
}
