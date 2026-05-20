<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('main_organization_id');
            $table->foreignId('user_id')->nullable();
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->string('invite_code')->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('main_organization_id', 'org_employees_main_org_fk')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'org_employees_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(['main_organization_id', 'status'], 'org_employees_status_idx');
            $table->index(['main_organization_id', 'email'], 'org_employees_email_idx');
        });

        Schema::create('organization_employee_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_employee_id');
            $table->foreignId('organization_id');
            $table->foreignId('organization_role_id');
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_employee_id', 'org_emp_assign_employee_fk')
                ->references('id')
                ->on('organization_employees')
                ->cascadeOnDelete();
            $table->foreign('organization_id', 'org_emp_assign_org_fk')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
            $table->foreign('organization_role_id', 'org_emp_assign_role_fk')
                ->references('id')
                ->on('organization_roles')
                ->cascadeOnDelete();
            $table->index(['organization_employee_id', 'organization_id'], 'org_emp_assign_lookup_idx');
            $table->index(['organization_id', 'status'], 'org_emp_assign_status_idx');
        });

        $this->backfillExistingWorkspaceMembers();
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_employee_assignments');
        Schema::dropIfExists('organization_employees');
    }

    private function backfillExistingWorkspaceMembers(): void
    {
        $teamRows = DB::table('teams')
            ->join('organizations', 'organizations.id', '=', 'teams.organization_id')
            ->leftJoin('users', 'users.id', '=', 'teams.user_id')
            ->whereNull('teams.deleted_at')
            ->whereNull('organizations.deleted_at')
            ->select([
                'teams.id',
                'teams.organization_id',
                'teams.user_id',
                'teams.organization_role_id',
                'teams.status',
                'teams.created_by',
                'teams.created_at',
                'teams.updated_at',
                'organizations.organization_type',
                'organizations.parent_organization_id',
                'users.email',
                'users.first_name',
                'users.last_name',
            ])
            ->orderBy('teams.id')
            ->get();

        $employeeIds = [];
        $assignmentIds = [];

        foreach ($teamRows as $teamRow) {
            $companyId = $teamRow->organization_type === 'branch' && $teamRow->parent_organization_id
                ? (int) $teamRow->parent_organization_id
                : (int) $teamRow->organization_id;

            $email = strtolower(trim((string) ($teamRow->email ?? '')));
            $userId = $teamRow->user_id ? (int) $teamRow->user_id : null;

            if ($companyId <= 0 || ($userId === null && $email === '')) {
                continue;
            }

            $employeeKey = $companyId.'|'.($userId ?? $email);

            if (! array_key_exists($employeeKey, $employeeIds)) {
                $existingEmployeeId = DB::table('organization_employees')
                    ->where('main_organization_id', $companyId)
                    ->when(
                        $userId !== null,
                        fn ($query) => $query->where('user_id', $userId),
                        fn ($query) => $query->where('email', $email)
                    )
                    ->value('id');

                if (! $existingEmployeeId) {
                    $existingEmployeeId = DB::table('organization_employees')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'main_organization_id' => $companyId,
                        'user_id' => $userId,
                        'email' => $email,
                        'first_name' => $teamRow->first_name,
                        'last_name' => $teamRow->last_name,
                        'status' => $teamRow->status === 'active' ? 'active' : 'pending',
                        'invited_by' => $teamRow->created_by,
                        'accepted_at' => $userId !== null ? ($teamRow->created_at ?? now()) : null,
                        'metadata' => json_encode(['source' => 'teams_backfill']),
                        'created_at' => $teamRow->created_at ?? now(),
                        'updated_at' => $teamRow->updated_at ?? now(),
                        'deleted_at' => null,
                    ]);
                }

                $employeeIds[$employeeKey] = (int) $existingEmployeeId;
            }

            $employeeId = $employeeIds[$employeeKey];
            $assignmentKey = $employeeId.'|'.(int) $teamRow->organization_id;

            if (array_key_exists($assignmentKey, $assignmentIds)) {
                continue;
            }

            $existingAssignmentId = DB::table('organization_employee_assignments')
                ->where('organization_employee_id', $employeeId)
                ->where('organization_id', (int) $teamRow->organization_id)
                ->value('id');

            if (! $existingAssignmentId) {
                $existingAssignmentId = DB::table('organization_employee_assignments')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'organization_employee_id' => $employeeId,
                    'organization_id' => (int) $teamRow->organization_id,
                    'organization_role_id' => (int) $teamRow->organization_role_id,
                    'status' => $teamRow->status === 'active' ? 'active' : 'pending',
                    'assigned_by' => $teamRow->created_by,
                    'assigned_at' => $teamRow->created_at ?? now(),
                    'activated_at' => $teamRow->status === 'active' ? ($teamRow->created_at ?? now()) : null,
                    'metadata' => json_encode([
                        'source' => 'teams_backfill',
                        'team_id' => (int) $teamRow->id,
                    ]),
                    'created_at' => $teamRow->created_at ?? now(),
                    'updated_at' => $teamRow->updated_at ?? now(),
                    'deleted_at' => null,
                ]);
            }

            $assignmentIds[$assignmentKey] = (int) $existingAssignmentId;
        }
    }
};
