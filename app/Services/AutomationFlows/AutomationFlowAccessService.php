<?php

namespace App\Services\AutomationFlows;

use App\Services\AddonStateService;
use Illuminate\Support\Facades\Schema;

class AutomationFlowAccessService
{
    private const BASE_TABLES = [
        'automation_flows',
        'automation_flow_versions',
        'automation_flow_runs',
        'automation_flow_run_steps',
    ];

    private const BUILDER_TABLES = [
        'automation_flow_assets',
        'automation_flow_node_secrets',
    ];

    public function __construct(
        private readonly AddonStateService $addonState,
    ) {
    }

    public function runtimeEnabled(): bool
    {
        return config('automation_flows.enabled');
    }

    public function addonName(): string
    {
        return 'Flow builder';
    }

    public function addonEnabledForOrganization(?int $organizationId): bool
    {
        return $this->addonState->isModuleEnabledForOrganization($this->addonName(), $organizationId);
    }

    public function baseSchemaReady(): bool
    {
        return $this->missingBaseTables() === [];
    }

    public function builderSchemaReady(): bool
    {
        return $this->missingBuilderTables() === [];
    }

    public function surfaceAvailableForOrganization(?int $organizationId): bool
    {
        return $this->runtimeEnabled()
            && $this->addonEnabledForOrganization($organizationId)
            && $this->baseSchemaReady();
    }

    public function builderAvailableForOrganization(?int $organizationId): bool
    {
        return $this->surfaceAvailableForOrganization($organizationId)
            && $this->builderSchemaReady();
    }

    public function availableForOrganization(?int $organizationId): bool
    {
        return $this->builderAvailableForOrganization($organizationId);
    }

    public function readinessReport(?int $organizationId = null): array
    {
        $runtimeEnabled = $this->runtimeEnabled();
        $addonEnabled = $organizationId === null
            ? null
            : $this->addonEnabledForOrganization($organizationId);
        $missingBaseTables = $this->missingBaseTables();
        $missingBuilderTables = $this->missingBuilderTables();
        $baseSchemaReady = $missingBaseTables === [];
        $builderSchemaReady = $missingBuilderTables === [];
        $surfaceReady = $runtimeEnabled
            && ($addonEnabled !== false)
            && $baseSchemaReady;
        $builderReady = $surfaceReady && $builderSchemaReady;

        return [
            'runtime_enabled' => $runtimeEnabled,
            'addon_enabled' => $addonEnabled,
            'base_schema_ready' => $baseSchemaReady,
            'builder_schema_ready' => $builderSchemaReady,
            'surface_ready' => $surfaceReady,
            'builder_ready' => $builderReady,
            'missing_base_tables' => $missingBaseTables,
            'missing_builder_tables' => $missingBuilderTables,
            'missing_tables' => array_values(array_merge($missingBaseTables, $missingBuilderTables)),
            'message' => $this->readinessMessage(
                $runtimeEnabled,
                $addonEnabled,
                $baseSchemaReady,
                $builderSchemaReady
            ),
        ];
    }

    private function missingBaseTables(): array
    {
        return array_values(array_filter(
            self::BASE_TABLES,
            static fn (string $table): bool => !Schema::hasTable($table)
        ));
    }

    private function missingBuilderTables(): array
    {
        return array_values(array_filter(
            self::BUILDER_TABLES,
            static fn (string $table): bool => !Schema::hasTable($table)
        ));
    }

    private function readinessMessage(
        bool $runtimeEnabled,
        ?bool $addonEnabled,
        bool $baseSchemaReady,
        bool $builderSchemaReady
    ): ?string {
        if (!$runtimeEnabled) {
            return __('Flow Builder runtime is disabled.');
        }

        if ($addonEnabled === false) {
            return __('Flow builder feature is not enabled for your organization.');
        }

        if (!$baseSchemaReady) {
            return __('Flow Builder base setup is incomplete. Run the latest migrations and try again.');
        }

        if (!$builderSchemaReady) {
            return __('Flow Builder setup is incomplete. Run the latest migrations and try again.');
        }

        return null;
    }
}
