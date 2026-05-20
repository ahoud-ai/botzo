<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Mail\CustomEmail;
use App\Mail\CustomEmailVerification;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;
use App\Services\EmailService;
use App\Services\PasswordResetService;
use App\Services\TeamService;
use App\Services\UserService;
use App\Support\EmailTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmailTemplateCoreFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'company_name'], ['value' => 'Botzo']);

        $this->storeTemplate('Registration', 'Welcome {{FirstName}}', '<p>Hello {{FirstName}}</p><p><a href="%7B%7BLink%7D%7D">{{Link}}</a></p>');
        $this->storeTemplate('Invite', 'Invitation from {{CompanyName}}', '<p>Hello {{FirstName}}</p><p><a href="%7B%7BLink%7D%7D">{{Link}}</a></p><p>{{Email}}</p>');
        $this->storeTemplate('Reset Password', 'Reset for {{FirstName}}', '<p><a href="%7B%7BLink%7D%7D">{{Link}}</a></p>');
        $this->storeTemplate('Password Reset Notification', 'Password updated for {{FirstName}}', '<p>Done</p>');
        $this->storeTemplate('Verify Email', 'Verify your email {{FirstName}}', '<p>Hello {{FirstName}}</p><p><a href="%7B%7BLink%7D%7D">{{Link}}</a></p>');
    }

    public function test_registration_template_is_queued_when_user_service_creates_user(): void
    {
        Mail::fake();

        $service = new UserService('user');
        $request = Request::create('/register', 'POST', [
            'first_name' => 'Rami',
            'last_name' => 'Ali',
            'email' => 'rami@example.com',
            'password' => 'password123',
        ]);

        $service->store($request);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) {
            return $mail->subject === 'Welcome Rami'
                && str_contains((string) $mail->body, 'Hello Rami')
                && str_contains((string) $mail->body, 'href="http')
                && ! str_contains((string) $mail->body, '%7B%7BLink%7D%7D');
        });
    }

    public function test_invite_template_is_queued_when_team_invite_is_created(): void
    {
        Mail::fake();

        $inviter = $this->createUser('inviter@example.com', 'Inviter');
        $this->createUser('invitee@example.com', 'Invitee');
        $organization = $this->createOrganization($inviter);
        $this->createActiveSubscription($organization->id);
        $ownerRole = $this->ownerRole();
        $managerRole = OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => 'Manager '.Str::random(5),
            'description' => 'Manager',
            'permissions' => ['contacts.view_all'],
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $inviter->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $inviter->id,
        ]);

        $this->actingAs($inviter, 'user');
        session()->put('current_organization', $organization->id);

        $request = Request::create('/team/invite', 'POST', [
            'email' => 'invitee@example.com',
            'organization_role_id' => $managerRole->id,
        ]);

        app(TeamService::class)->invite($request);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) {
            return $mail->subject === 'Invitation from Botzo'
                && str_contains((string) $mail->body, 'Invitee')
                && str_contains((string) $mail->body, 'invitee@example.com')
                && str_contains((string) $mail->body, 'href="http')
                && ! str_contains((string) $mail->body, '%7B%7BLink%7D%7D');
        });
    }

    public function test_team_reinvite_before_expiry_rotates_code_and_updates_role(): void
    {
        Mail::fake();

        $inviter = $this->createUser('reinvite-owner@example.com', 'Owner');
        $organization = $this->createOrganization($inviter);
        $this->createActiveSubscription($organization->id);
        $ownerRole = $this->ownerRole();
        $firstRole = OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => 'Supervisor '.Str::random(5),
            'description' => 'Supervisor',
            'permissions' => ['contacts.view_all'],
        ]);
        $secondRole = OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => 'Agent '.Str::random(5),
            'description' => 'Agent',
            'permissions' => ['chats.view'],
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $inviter->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $inviter->id,
        ]);

        $this->actingAs($inviter, 'user');
        session()->put('current_organization', $organization->id);

        app(TeamService::class)->invite(Request::create('/team/invite', 'POST', [
            'email' => 'reinvitee@example.com',
            'organization_role_id' => $firstRole->id,
        ]));

        $invite = TeamInvite::query()
            ->where('organization_id', $organization->id)
            ->where('email', 'reinvitee@example.com')
            ->firstOrFail();
        $originalCode = $invite->code;

        app(TeamService::class)->invite(Request::create('/team/invite', 'POST', [
            'email' => 'reinvitee@example.com',
            'organization_role_id' => $secondRole->id,
        ]));

        $invite->refresh();

        $this->assertSame($secondRole->id, $invite->organization_role_id);
        $this->assertNotSame($originalCode, $invite->code);
        $this->assertSame(1, TeamInvite::query()
            ->where('organization_id', $organization->id)
            ->where('email', 'reinvitee@example.com')
            ->count());
    }

    public function test_team_invite_rejects_existing_active_member(): void
    {
        Mail::fake();

        $inviter = $this->createUser('existing-owner@example.com', 'Owner');
        $invitee = $this->createUser('existing-member@example.com', 'Member');
        $organization = $this->createOrganization($inviter);
        $ownerRole = $this->ownerRole();
        $memberRole = OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => 'Member '.Str::random(5),
            'description' => 'Member',
            'permissions' => ['contacts.view_all'],
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $inviter->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $inviter->id,
        ]);

        Team::create([
            'organization_id' => $organization->id,
            'user_id' => $invitee->id,
            'organization_role_id' => $memberRole->id,
            'status' => 'active',
            'created_by' => $inviter->id,
        ]);

        $this->actingAs($inviter, 'user');
        session()->put('current_organization', $organization->id);

        try {
            app(TeamService::class)->invite(Request::create('/team/invite', 'POST', [
                'email' => $invitee->email,
                'organization_role_id' => $memberRole->id,
            ]));

            $this->fail('Expected duplicate active team invite to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertSame(0, TeamInvite::query()
            ->where('organization_id', $organization->id)
            ->where('email', $invitee->email)
            ->count());
    }

    public function test_password_reset_templates_are_dispatched_for_reset_and_completion(): void
    {
        Mail::fake();

        $user = $this->createUser('reset@example.com', 'Reset');
        $service = app(PasswordResetService::class);

        $resetLink = $service->generateResetLink($user->email);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) {
            return $mail->subject === 'Reset for Reset'
                && str_contains((string) $mail->body, 'href="http')
                && ! str_contains((string) $mail->body, '%7B%7BLink%7D%7D');
        });

        parse_str((string) parse_url($resetLink, PHP_URL_QUERY), $query);
        $token = (string) ($query['token'] ?? '');
        $request = Request::create('/password/reset', 'POST', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password-123',
        ]);

        $service->resetPassword($request);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) {
            return $mail->subject === 'Password updated for Reset';
        });
    }

    public function test_verify_email_template_is_sent_from_user_model(): void
    {
        Mail::fake();

        $user = $this->createUser('verify@example.com', 'Verify');
        $user->email_verified_at = null;
        $user->save();

        $user->sendEmailVerificationNotification();

        Mail::assertSent(CustomEmailVerification::class);

        $html = (new CustomEmailVerification($user))->render();

        $this->assertStringContainsString('Hello Verify', $html);
        $this->assertStringContainsString('href="http', $html);
        $this->assertStringNotContainsString('{{FirstName}}', $html);
        $this->assertStringNotContainsString('{verificationLink}', $html);
        $this->assertStringNotContainsString('%7B%7BLink%7D%7D', $html);
    }

    public function test_signup_with_verify_email_enabled_sends_only_verification_email(): void
    {
        Mail::fake();

        Setting::updateOrCreate(['key' => 'verify_email'], ['value' => '1']);

        $response = $this->post('/signup', [
            'first_name' => 'Alaa',
            'last_name' => 'Tester',
            'email' => 'signup-verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('user.organization.index'));

        Mail::assertSent(CustomEmailVerification::class);
        Mail::assertNotQueued(CustomEmail::class);
    }

    public function test_social_registration_email_uses_default_login_link(): void
    {
        Mail::fake();

        $controller = new AuthController();
        $method = new \ReflectionMethod($controller, 'resolveOrCreateSocialUser');
        $method->setAccessible(true);

        $user = $method->invoke($controller, 'google', (object) [
            'id' => 'google-'.Str::random(10),
            'email' => 'social-new+'.Str::lower(Str::random(8)).'@example.com',
            'name' => 'Social Tester',
        ]);

        $this->assertInstanceOf(User::class, $user);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) {
            return $mail->subject === 'Welcome Social'
                && str_contains((string) $mail->body, 'href="http')
                && str_contains((string) $mail->body, url('/login'))
                && ! str_contains((string) $mail->body, '{{Link}}');
        });
    }

    public function test_registration_editor_catalog_hides_link_placeholder(): void
    {
        $this->assertNotContains('{{Link}}', EmailTemplateCatalog::editorPlaceholdersFor('Registration'));
        $this->assertContains('{{Link}}', EmailTemplateCatalog::editorPlaceholdersFor('Verify Email'));
    }

    public function test_email_template_update_rejects_unsupported_placeholders_for_template(): void
    {
        $template = EmailTemplate::query()->where('name', 'Verify Email')->firstOrFail();

        try {
            app(EmailService::class)->updateTemplate(
                Request::create('/admin/settings/email-templates/'.$template->id, 'PUT', [
                    'subject' => 'Verify {{FirstName}}',
                    'body' => '<p>{{InvitedByFullName}}</p>',
                ]),
                $template->id
            );

            $this->fail('Expected invalid verify email placeholder usage to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('body', $exception->errors());
            $this->assertStringContainsString('{{InvitedByFullName}}', $exception->errors()['body'][0]);
        }
    }

    private function storeTemplate(string $name, string $subject, string $body): void
    {
        EmailTemplate::updateOrCreate(
            ['name' => $name],
            [
                'subject' => $subject,
                'body' => $body,
                'updated_by' => 1,
                'updated_at' => now(),
            ]
        );
    }

    private function createUser(string $email, string $firstName): User
    {
        return User::create([
            'first_name' => $firstName,
            'last_name' => 'Tester',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
    }

    private function createOrganization(User $creator): Organization
    {
        return Organization::create([
            'name' => 'Organization '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([
                'addons' => [],
            ]),
        ]);
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::firstOrCreate(
            ['organization_id' => null, 'name' => 'Owner'],
            ['description' => 'Owner', 'permissions' => ['*']]
        );
    }

    private function createActiveSubscription(int $organizationId): Subscription
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Plan '.Str::random(5),
            'price' => 10,
            'period' => 'monthly',
            'metadata' => json_encode([
                'team_limit' => 5,
                'branches_limit' => 1,
                'addons' => [],
            ]),
            'status' => 'active',
        ]);

        return Subscription::create([
            'organization_id' => $organizationId,
            'plan_id' => $plan->id,
            'payment_details' => null,
            'start_date' => now(),
            'valid_until' => now()->addMonth(),
            'status' => 'active',
        ]);
    }
}
