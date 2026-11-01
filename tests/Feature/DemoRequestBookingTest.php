<?php

namespace Tests\Feature;

use App\Mail\DemoRequestAlertMail;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\DemoRequest;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebWidget;
use App\Services\AddonStateService;
use App\Services\WebWidget\SiteWidget;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * Mochi on botzo.net books demos: a form card in the chat saves the request, the team is emailed,
 * and the admin page lists the requests with a WhatsApp link and a status.
 */
class DemoRequestBookingTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const ORIGIN = 'https://shop.example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
        config(['logging.channels.intellireply' => ['driver' => 'null']]);
        Mail::fake();
        // Rolled back with the test's transaction; counts below start from an empty table.
        DemoRequest::query()->delete();
        Setting::updateOrCreate(['key' => 'email'], ['value' => 'team@botzo.test']);
        Setting::updateOrCreate(['key' => 'frontend_contact_phone_primary'], ['value' => '+966 50 000 0000']);
    }

    public function test_mochi_shows_the_booking_form_and_the_visitor_books_a_demo(): void
    {
        $widget = $this->siteWidget();
        $token = $this->openSession($widget);

        $this->fakeReply('أبشر! عبّ بياناتك وفريقنا يتواصل معك على واتساب 👇 [[CARD:demo]]');
        $this->postJson($this->url($widget, 'messages'), ['text' => 'أبي عرض توضيحي'], $this->headers($token))
            ->assertOk()
            ->assertJsonPath('messages.1.action.type', 'demo_form');
        Http::assertSent(fn ($request) => str_contains(collect($request['messages'] ?? [])->where('role', 'system')->pluck('content')->implode("\n"), '[[CARD:demo]]'));

        $response = $this->postJson($this->url($widget, 'demo-request'), [
            'full_name' => 'سارة العتيبي',
            'phone' => '0551234567',
            'business_activity' => 'متجر عطور',
            'preferred_time' => 'evening',
        ], $this->headers($token))->assertOk();

        $demo = DemoRequest::sole();
        $this->assertSame(['سارة العتيبي', '+966551234567', 'متجر عطور', 'evening', 'mochi', 'new'], [
            $demo->full_name, $demo->phone, $demo->business_activity, $demo->preferred_time, $demo->source, $demo->status,
        ]);
        $this->assertNull($demo->email);

        $contact = Contact::findOrFail($demo->contact_id);
        $this->assertSame('سارة العتيبي', $contact->first_name, 'The inbox shows the visitor by name.');

        $response->assertJsonPath('awaiting_team', true)
            ->assertJsonPath('messages.0.from', 'visitor')
            ->assertJsonPath('messages.1.from', 'assistant')
            ->assertJsonPath('messages.1.action.type', 'demo_done')
            ->assertJsonPath('messages.1.action.whatsapp_url', 'https://wa.me/966500000000?text='.rawurlencode('السلام عليكم، أرسلت طلب عرض توضيحي من موقع بوتزو'));
        $this->assertStringContainsString('+966551234567', (string) $response->json('messages.0.text'));
        $this->assertStringContainsString('واتساب', (string) $response->json('messages.1.text'));
        $this->assertSame(3, Chat::where('contact_id', $contact->id)->count() - 1, 'Request summary and confirmation are in the conversation.');

        Mail::assertSent(DemoRequestAlertMail::class, fn ($mail) => $mail->hasTo('team@botzo.test')
            && $mail->demoRequest->is($demo)
            && $mail->whatsappUrl === 'https://wa.me/966551234567');
    }

    public function test_bad_details_and_repeat_bookings_are_refused(): void
    {
        $widget = $this->siteWidget();
        $token = $this->openSession($widget);
        $this->fakeReply('أكيد [[CARD:demo]]');
        $this->postJson($this->url($widget, 'messages'), ['text' => 'demo'], $this->headers($token))->assertOk();

        $this->postJson($this->url($widget, 'demo-request'), ['full_name' => 'سارة', 'phone' => '12'], $this->headers($token))->assertStatus(422);
        $this->postJson($this->url($widget, 'demo-request'), ['full_name' => '', 'phone' => '0551234567'], $this->headers($token))->assertStatus(422);
        $this->postJson($this->url($widget, 'demo-request'), ['full_name' => 'سارة', 'phone' => '0551234567', 'preferred_time' => 'midnight'], $this->headers($token))->assertStatus(422);

        $this->postJson($this->url($widget, 'demo-request'), ['full_name' => 'سارة', 'phone' => '0551234567'], $this->headers($token))->assertOk();
        $this->postJson($this->url($widget, 'demo-request'), ['full_name' => 'سارة', 'phone' => '0551234567'], $this->headers($token))
            ->assertStatus(429)->assertJsonPath('error', 'already_requested');
        $this->assertSame(1, DemoRequest::count());
    }

    public function test_only_botzos_own_widget_books_demos(): void
    {
        $customer = $this->widget();
        $token = $this->openSession($customer);
        $this->fakeReply('أكيد [[CARD:demo]]');

        $this->postJson($this->url($customer, 'messages'), ['text' => 'demo'], $this->headers($token))
            ->assertOk()
            ->assertJsonMissingPath('messages.1.action');
        $this->postJson($this->url($customer, 'demo-request'), ['full_name' => 'سارة', 'phone' => '0551234567'], $this->headers($token))
            ->assertForbidden();
        $this->assertSame(0, DemoRequest::count());
    }

    public function test_the_website_form_also_alerts_the_team(): void
    {
        $this->post('/demo-requests', [
            'full_name' => 'Ali',
            'phone' => '+966 55 765 4321',
            'email' => 'ali@example.com',
            'business_activity' => 'Clinic',
        ])->assertRedirect();

        $demo = DemoRequest::sole();
        $this->assertSame(['website', '+966557654321', 'ali@example.com'], [$demo->source, $demo->phone, $demo->email]);
        Mail::assertSent(DemoRequestAlertMail::class, fn ($mail) => $mail->hasTo('team@botzo.test'));
    }

    public function test_admins_see_requests_filter_them_and_update_the_status(): void
    {
        $new = DemoRequest::create(['full_name' => 'سارة', 'phone' => '+966551234567', 'source' => 'mochi', 'status' => 'new']);
        DemoRequest::create(['full_name' => 'Ali', 'phone' => '+966557654321', 'email' => 'ali@example.com', 'source' => 'website', 'status' => 'done']);
        $admin = $this->adminUser('admin');

        $this->actingAs($admin, 'admin')->get('/admin/demo-requests?status=new')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/DemoRequest/Index')
                ->where('summary.total', 2)
                ->where('summary.new', 1)
                ->has('rows.data', 1)
                ->where('rows.data.0.full_name', 'سارة')
                ->where('rows.data.0.source', 'mochi')
                ->where('rows.data.0.whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/966551234567?text=')));

        $this->actingAs($admin, 'admin')->get('/admin/demo-requests?search=ali@')
            ->assertInertia(fn ($page) => $page->has('rows.data', 1)->where('rows.data.0.full_name', 'Ali'));

        $this->actingAs($admin, 'admin')->post('/admin/demo-requests/'.$new->id.'/status', ['status' => 'contacted'])->assertRedirect();
        $this->assertSame('contacted', $new->fresh()->status);

        $this->actingAs($admin, 'admin')->post('/admin/demo-requests/'.$new->id.'/status', ['status' => 'lost'])->assertSessionHasErrors('status');
    }

    public function test_other_admin_roles_need_the_demo_requests_permission(): void
    {
        $role = Role::create(['name' => 'support '.Str::random(6)]);
        $staff = $this->adminUser(strtolower($role->name));

        $this->actingAs($staff, 'admin')->get('/admin/demo-requests')->assertForbidden();
    }

    private function siteWidget(): WebWidget
    {
        $widget = $this->widget();
        app(SiteWidget::class)->remember($widget->organization);

        return $widget;
    }

    private function widget(): WebWidget
    {
        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => true,
            'model' => 'gpt-4o-mini',
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
        ]], true);

        return WebWidget::create([
            'organization_id' => $organization->id,
            'public_key' => WebWidget::generatePublicKey(),
            'is_enabled' => true,
            'allowed_domains' => ['shop.example.com'],
            'settings' => [],
        ])->load('organization');
    }

    private function fakeReply(string $text): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => $text]]]]),
            '*' => Http::response([], 500),
        ]);
    }

    private function adminUser(string $role): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    private function openSession(WebWidget $widget): string
    {
        return (string) $this->postJson($this->url($widget, 'session'), [], ['Origin' => self::ORIGIN])->json('token');
    }

    private function headers(string $token): array
    {
        return ['Origin' => self::ORIGIN, 'X-Widget-Token' => $token];
    }

    private function url(WebWidget $widget, string $path): string
    {
        return '/api/widget/'.$widget->public_key.'/'.$path;
    }
}
