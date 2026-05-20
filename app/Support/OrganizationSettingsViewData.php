<?php

namespace App\Support;

use App\Models\Addon;
use App\Models\Contact;
use App\Models\Organization;

class OrganizationSettingsViewData
{
    public function general(int $organizationId): array
    {
        $contactModel = new Contact;
        $organization = Organization::query()
            ->with('parentOrganization')
            ->findOrFail($organizationId);

        return [
            'title' => __('Settings'),
            'settings' => $organization,
            'timezones' => config('formats.timezones'),
            'countries' => config('formats.countries'),
            'modules' => Addon::query()
                ->whereIn('name', SaClientPlanProfile::planAddonNames())
                ->get(),
            'contactGroups' => $contactModel->getAllContactGroups($organizationId),
            'organizationProfileContext' => app(OrganizationProfileContext::class)->build($organization),
        ];
    }
}
