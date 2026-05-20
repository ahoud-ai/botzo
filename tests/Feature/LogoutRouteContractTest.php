<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogoutRouteContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_route_rejects_get_requests(): void
    {
        $this->get('/logout')->assertStatus(405);
    }

    public function test_logout_clears_user_and_admin_guards_and_invalidates_session(): void
    {
        $user = $this->createAccount('user');
        $admin = $this->createAccount('admin');

        $this->actingAs($user, 'user');
        $this->actingAs($admin, 'admin');

        $response = $this->withSession([
                'current_organization' => 123,
                'profile_modal_open' => true,
            ])
            ->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest('user');
        $this->assertGuest('admin');
        $response->assertSessionMissing('current_organization');
        $response->assertSessionMissing('profile_modal_open');
    }

    private function createAccount(string $role): User
    {
        return User::query()->create([
            'first_name' => ucfirst($role),
            'last_name' => 'Logout',
            'email' => sprintf('%s-logout+%s@example.com', $role, Str::random(8)),
            'password' => Hash::make('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }
}
