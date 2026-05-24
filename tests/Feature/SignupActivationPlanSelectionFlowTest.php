<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignupActivationPlanSelectionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_signup_activate_email_and_select_a_zero_amount_plan(): void
    {
        $this->prepareSignupBillingContext();

        $email = 'qa-onboarding+'.Str::random(8).'@example.com';

        $signupResponse = $this->post('/signup', [
            'first_name' => 'QA',
            'last_name' => 'Owner',
            'organization_name' => 'QA Workspace',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $signupResponse->assertRedirect('/dashboard');
        $this->assertAuthenticated('user');

        $user = User::where('email', $email)->firstOrFail();
        $organization = Organization::where('name', 'QA Workspace')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
        ]);

        $this->withSession(['current_organization' => $organization->id])
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $this->actingAs($user)
            ->withSession(['current_organization' => $organization->id])
            ->get($verificationUrl)
            ->assertRedirect('/dashboard');

        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->actingAs($user)
            ->withSession(['current_organization' => $organization->id])
            ->get('/subscription')
            ->assertOk();

        $plan = SubscriptionPlan::where('name', 'QA Free Starter')->firstOrFail();

        $this->actingAs($user)
            ->withSession(['current_organization' => $organization->id])
            ->post('/subscription', [
                'plan' => $plan->id,
            ])
            ->assertRedirect(route('user.billing.index'));

        $subscription = Subscription::where('organization_id', $organization->id)->first();

        $this->assertNotNull($subscription);
        $this->assertSame($plan->id, (int) $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
    }

    private function prepareSignupBillingContext(): void
    {
        foreach ([
            'verify_email' => '1',
            'smtp_email_active' => '0',
            'trial_period' => '0',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Universal owner role for onboarding tests',
                'permissions' => ['*'],
            ]
        );

        PaymentGateway::updateOrCreate(
            ['name' => 'Moyasar'],
            [
                'is_active' => 0,
                'metadata' => null,
            ]
        );

        SubscriptionPlan::create([
            'name' => 'QA Free Starter',
            'name_ar' => 'باقة اختبار مجانية',
            'name_en' => 'QA Free Starter',
            'price' => 0,
            'period' => 'monthly',
            'metadata' => json_encode([
                'tier_rank' => 1,
                'campaign_limit' => 10,
                'message_limit' => 100,
                'contacts_limit' => 100,
                'canned_replies_limit' => 10,
                'team_limit' => 3,
                'branches_limit' => 1,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);
    }
}
