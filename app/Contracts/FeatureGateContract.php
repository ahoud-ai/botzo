<?php

namespace App\Contracts;

interface FeatureGateContract
{
    public function isAddonEnabled(string $name): bool;

    public function isModuleEnabledForOrganization(string $name, ?int $organizationId = null): bool;
}
