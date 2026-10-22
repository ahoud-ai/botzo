<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\WebWidget;
use App\Services\WebWidget\SiteWidget;
use App\Services\WebWidget\WebWidgetDefaults;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AdminWebWidgetSettingsTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_admins_change_the_defaults_new_widgets_start_from(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/settings/features/web-widget')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Setting/Features/WebWidget')->where('defaults.theme', 'mochi')->where('site', null));

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/settings/features/web-widget/defaults', [
                'theme' => 'classic',
                'accent' => '#112233',
                'accent_secondary' => '#445566',
                'position' => 'right',
                'assistant_name' => 'سارة',
                'welcome_text' => 'أهلاً',
                'suggestions' => ['الأسعار', '', '', ''],
                'voice' => 'marin',
                'call_max_minutes' => 3,
                'daily_minutes_cap' => 120,
            ])
            ->assertSessionHasNoErrors();

        $defaults = app(WebWidgetDefaults::class);
        $this->assertSame('classic', $defaults->settings()['theme']);
        $this->assertSame('سارة', $defaults->settings()['assistant_name']);
        $this->assertSame(['call_max_minutes' => 3, 'daily_minutes_cap' => 120], $defaults->voiceLimits());
        $this->assertSame('classic', (new WebWidget())->settingsWithDefaults()['theme']);
    }

    public function test_admins_control_the_botzo_site_widget_and_the_public_pages_load_it(): void
    {
        [, $organization] = $this->createOwnerContext([], true);
        app(SiteWidget::class)->remember($organization);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/settings/features/web-widget/site', [
                'is_enabled' => true,
                'allowed_domains' => ['botzo.net'],
                'theme' => 'mochi',
                'accent' => '#22D366',
                'accent_secondary' => '#2F7BFF',
                'position' => 'left',
                'assistant_name' => 'موتشي',
                'business_label' => 'بوتزو',
                'welcome_text' => 'أنا موتشي',
                'suggestions' => ['كم الأسعار؟', '', '', ''],
                'pages' => [['label' => 'الأسعار', 'url' => '/pricing']],
                'daily_reply_limit' => 500,
            ])
            ->assertSessionHasNoErrors();

        $widget = WebWidget::where('organization_id', $organization->id)->sole();
        $this->assertTrue($widget->is_enabled);
        $this->assertSame('بوتزو', $widget->settings['business_label']);
        $this->assertSame(['/pricing'], array_column($widget->settings['pages'], 'url'));

        $this->get('/')->assertSee('data-key="'.$widget->public_key.'"', false);

        $widget->update(['is_enabled' => false]);
        app(SiteWidget::class)->forget();
        $this->get('/')->assertDontSee($widget->public_key, false);
    }

    public function test_customers_cannot_reach_the_admin_widget_settings(): void
    {
        [$user] = $this->createOwnerContext([], true);

        $this->actingAs($user, 'user')->get('/admin/settings/features/web-widget')->assertRedirect();
    }

    private function admin(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }
}
