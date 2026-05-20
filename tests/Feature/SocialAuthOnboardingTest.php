<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Services\SocialLoginService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SocialAuthOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_google_callback_creates_user_without_workspace_or_subscription(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '0']);
        $this->bindSocialLoginService(
            googleUser: $this->fakeProviderUser('google-001', 'google-new+'.Str::random(6).'@example.com', 'Google New User')
        );

        $response = $this->get('/google/callback');

        $response->assertRedirect(route('user.organization.index'));
        $this->assertAuthenticated('user');

        $user = User::query()->whereNotNull('google_id')->latest('id')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-001', $user->google_id);
        $this->assertDatabaseMissing('teams', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('subscriptions', []);
    }

    public function test_google_callback_links_existing_user_by_email_without_auto_workspace_creation(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '0']);

        $user = User::create([
            'first_name' => 'Existing',
            'last_name' => 'Google',
            'email' => 'google-existing+'.Str::random(6).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'status' => 1,
            'language' => 'en',
            'email_verified_at' => now(),
        ]);

        $this->bindSocialLoginService(
            googleUser: $this->fakeProviderUser('google-002', $user->email, 'Existing Google User')
        );

        $response = $this->get('/google/callback');

        $response->assertRedirect(route('user.organization.index'));
        $this->assertAuthenticated('user');
        $user->refresh();
        $this->assertSame('google-002', $user->google_id);
        $this->assertDatabaseMissing('teams', ['user_id' => $user->id]);
    }

    public function test_facebook_callback_creates_user_without_workspace_or_subscription(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '0']);
        $this->bindSocialLoginService(
            facebookUser: $this->fakeProviderUser('facebook-001', 'facebook-new+'.Str::random(6).'@example.com', 'Facebook New User')
        );

        $response = $this->get('/facebook/callback');

        $response->assertRedirect(route('user.organization.index'));
        $this->assertAuthenticated('user');

        $user = User::query()->whereNotNull('facebook_id')->latest('id')->first();
        $this->assertNotNull($user);
        $this->assertSame('facebook-001', $user->facebook_id);
        $this->assertDatabaseMissing('teams', ['user_id' => $user->id]);
    }

    public function test_facebook_callback_links_existing_user_by_email_without_auto_workspace_creation(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '0']);

        $user = User::create([
            'first_name' => 'Existing',
            'last_name' => 'Facebook',
            'email' => 'facebook-existing+'.Str::random(6).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'status' => 1,
            'language' => 'en',
            'email_verified_at' => now(),
        ]);

        $this->bindSocialLoginService(
            facebookUser: $this->fakeProviderUser('facebook-002', $user->email, 'Existing Facebook User')
        );

        $response = $this->get('/facebook/callback');

        $response->assertRedirect(route('user.organization.index'));
        $this->assertAuthenticated('user');
        $user->refresh();
        $this->assertSame('facebook-002', $user->facebook_id);
        $this->assertDatabaseMissing('teams', ['user_id' => $user->id]);
    }

    public function test_inactive_existing_social_account_is_rejected_instead_of_creating_duplicate_user(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_email_active'], ['value' => '0']);

        $user = new User();
        $user->first_name = 'Inactive';
        $user->last_name = 'Google';
        $user->email = 'inactive-google+'.Str::random(6).'@example.com';
        $user->password = Hash::make('password123');
        $user->role = 'user';
        $user->status = 0;
        $user->language = 'en';
        $user->email_verified_at = now();
        $user->save();

        $this->bindSocialLoginService(
            googleUser: $this->fakeProviderUser('google-003', $user->email, 'Inactive Google User')
        );

        $response = $this->get('/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'error'
                && ($status['message'] ?? null) === __('This account is not available for social login.');
        });
        $this->assertGuest('user');
    }

    public function test_facebook_callback_flashes_status_when_provider_fails(): void
    {
        $this->bindSocialLoginService(
            facebookUser: new RuntimeException('Token exchange failed')
        );

        $response = $this->get('/facebook/callback?code=test-code&state=test-state');

        $response->assertRedirect('/login');
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'error'
                && ($status['message'] ?? null) === __('Registration failed, please try again.');
        });
        $this->assertGuest('user');
    }

    private function bindSocialLoginService(mixed $googleUser = null, mixed $facebookUser = null): void
    {
        app()->instance(SocialLoginService::class, new class($googleUser, $facebookUser) extends SocialLoginService {
            public function __construct(
                private readonly mixed $googleUser,
                private readonly mixed $facebookUser,
            ) {
            }

            public function makeGoogleDriver(?string $redirect = null)
            {
                return new FakeSocialDriver($this->googleUser, $redirect);
            }

            public function makeFacebookDriver(?string $redirect = null)
            {
                return new FakeSocialDriver($this->facebookUser, $redirect);
            }
        });
    }

    private function fakeProviderUser(string $id, string $email, string $name): object
    {
        return (object) [
            'id' => $id,
            'email' => $email,
            'name' => $name,
            'first_name' => explode(' ', $name)[0] ?? 'User',
            'last_name' => explode(' ', $name)[1] ?? 'Test',
            'user' => [
                'name' => $name,
                'given_name' => explode(' ', $name)[0] ?? 'User',
                'family_name' => explode(' ', $name)[1] ?? 'Test',
            ],
        ];
    }
}

final class FakeSocialDriver
{
    public function __construct(
        private readonly mixed $user,
        private readonly ?string $redirectUrl = null,
    ) {
    }

    public function stateless(): self
    {
        return $this;
    }

    public function with(array $parameters): self
    {
        return $this;
    }

    public function redirect()
    {
        return new class($this->redirectUrl ?? '/login')
        {
            public function __construct(private readonly string $targetUrl)
            {
            }

            public function getTargetUrl(): string
            {
                return $this->targetUrl;
            }
        };
    }

    public function fields(array $fields): self
    {
        return $this;
    }

    public function user(): mixed
    {
        if ($this->user instanceof \Throwable) {
            throw $this->user;
        }

        return $this->user;
    }
}
