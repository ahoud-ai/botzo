<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminSystemOwnerProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_owner_cannot_be_deleted_by_another_full_admin(): void
    {
        $owner = $this->createAdminUser(['is_system_owner' => true]);
        $otherAdmin = $this->createAdminUser();

        $response = $this->actingAs($otherAdmin, 'admin')
            ->delete('/admin/team/users/'.$owner->id);

        $response->assertRedirect();
        $response->assertSessionHas('status.type', 'error');

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'deleted_at' => null,
        ]);
    }

    public function test_system_owner_role_cannot_be_changed(): void
    {
        $owner = $this->createAdminUser(['is_system_owner' => true]);
        $staffRole = $this->createRole('Staff');

        $request = Request::create('/admin/team/users/'.$owner->id, 'PUT', [
            'first_name' => $owner->first_name,
            'last_name' => $owner->last_name,
            'email' => $owner->email,
            'role' => $staffRole->uuid,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The primary system owner role cannot be changed.');

        try {
            (new UserService('admin'))->update($request, $owner->id);
        } finally {
            $this->assertDatabaseHas('users', [
                'id' => $owner->id,
                'role' => 'admin',
                'is_system_owner' => true,
                'deleted_at' => null,
            ]);
        }
    }

    public function test_system_owner_cannot_be_suspended(): void
    {
        $owner = $this->createAdminUser(['is_system_owner' => true]);
        $this->createAdminUser();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The primary system owner cannot be suspended.');

        try {
            (new UserService('admin'))->suspend($owner->id);
        } finally {
            $this->assertDatabaseHas('users', [
                'id' => $owner->id,
                'status' => 1,
                'is_system_owner' => true,
            ]);
        }
    }

    public function test_additional_full_admin_can_be_demoted_when_system_owner_remains_active(): void
    {
        $this->createAdminUser(['is_system_owner' => true]);
        $additionalAdmin = $this->createAdminUser();
        $staffRole = $this->createRole('Staff');

        $request = Request::create('/admin/team/users/'.$additionalAdmin->id, 'PUT', [
            'first_name' => $additionalAdmin->first_name,
            'last_name' => $additionalAdmin->last_name,
            'email' => $additionalAdmin->email,
            'role' => $staffRole->uuid,
        ]);

        (new UserService('admin'))->update($request, $additionalAdmin->id);

        $this->assertDatabaseHas('users', [
            'id' => $additionalAdmin->id,
            'role' => $staffRole->name,
            'is_system_owner' => false,
            'deleted_at' => null,
        ]);
    }

    public function test_additional_full_admin_can_be_deleted_when_system_owner_remains_active(): void
    {
        $this->createAdminUser(['is_system_owner' => true]);
        $additionalAdmin = $this->createAdminUser();

        (new UserService('admin'))->destroy($additionalAdmin->id);

        $this->assertSoftDeleted('users', [
            'id' => $additionalAdmin->id,
        ]);
    }

    public function test_last_active_system_admin_cannot_be_deleted(): void
    {
        $onlyAdmin = $this->createAdminUser();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('At least one active system administrator must remain before this action.');

        try {
            (new UserService('admin'))->destroy($onlyAdmin->id);
        } finally {
            $this->assertDatabaseHas('users', [
                'id' => $onlyAdmin->id,
                'deleted_at' => null,
            ]);
        }
    }

    private function createAdminUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin+'.Str::random(10).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function createRole(string $name): Role
    {
        return Role::create([
            'name' => $name.' '.Str::random(8),
        ]);
    }
}
