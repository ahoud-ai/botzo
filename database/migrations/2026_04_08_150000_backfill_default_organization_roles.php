<?php

use App\Models\Organization;
use App\Services\OrganizationDefaultRoleService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(OrganizationDefaultRoleService::class);

        Organization::query()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($organizations) use ($service) {
                foreach ($organizations as $organization) {
                    $service->ensureDefaultsForOrganization($organization);
                }
            });
    }

    public function down(): void
    {
        // Preset roles are additive business data and are not removed automatically.
    }
};
