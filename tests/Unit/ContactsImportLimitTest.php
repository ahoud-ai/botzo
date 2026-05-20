<?php

namespace Tests\Unit;

use App\Imports\ContactsImport;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactsImportLimitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_import_respects_inherited_parent_contact_limit_for_branch_workspaces(): void
    {
        $user = User::create([
            'first_name' => 'Import',
            'last_name' => 'Tester',
            'email' => 'import+'.Str::random(8).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $parent = Organization::create([
            'name' => 'Parent '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $user->id,
            'organization_type' => 'main',
            'metadata' => json_encode([]),
        ]);

        $branch = Organization::create([
            'name' => 'Branch '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $user->id,
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
            'metadata' => json_encode([]),
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Plan '.Str::random(4),
            'name_ar' => 'خطة '.Str::random(4),
            'name_en' => 'Plan '.Str::random(4),
            'price' => 99.00,
            'period' => 'monthly',
            'metadata' => json_encode([
                'contacts_limit' => 0,
                'message_limit' => 10,
            ]),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $this->actingAs($user);
        session(['current_organization' => $branch->id]);

        $import = new ContactsImport();

        $result = $import->model([
            'first_name' => 'Blocked',
            'last_name' => 'Contact',
            'phone' => '+966501234520',
            'email' => 'blocked@example.com',
        ]);

        $this->assertNull($result);
        $this->assertSame(1, $import->getFailedImportsDueToLimit());
        $this->assertDatabaseCount('contacts', 0);
    }
}
