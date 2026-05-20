<?php

namespace App\Http\Controllers;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreProfile;
use App\Http\Requests\StoreProfilePassword;
use App\Http\Requests\StoreProfileAddress;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationHierarchyService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Hash;
use Redirect;

class ProfileController extends BaseController
{
    public function update(StoreProfile $request)
    {
        $first_name = $request->first_name;
        $last_name = $request->last_name;
        $email = $request->email;
        $phone = $request->filled('phone') ? phone($request->phone)->formatE164() : null;
        $language = $request->language;

        // Get current user language before update
        $currentUser = auth()->user();
        $oldLanguage = $currentUser->language ?? 'en';
        
        $response = User::where('id', auth()->user()->id)->update([
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'language' => $language,
        ]);

        // Set the session locale to the user's selected language
        if ($language) {
            session(['locale' => $language]);
        }

        // Check if language was changed and add refresh parameter
        $needsRefresh = $language && $language !== $oldLanguage;
        
        if ($needsRefresh) {
            // For Inertia.js, we need to redirect with the refresh parameter
            return redirect()->back()->with([
                'status' => [
                    'type' => 'success', 
                    'message' => __('Profile updated successfully!')
                ],
                'refresh_lang' => true
            ]);
        }

        return Redirect::back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('Profile updated successfully!')
            ]
        );
    }

    public function updatePassword(StoreProfilePassword $request)
    {
        $old_password = $request->old_password;
        $password = Hash::make($request->password);
        $user = auth()->user();

        $response = User::where('id', $user->id)->update([
            'password' => $password,
        ]);

        return Redirect::back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('Profile updated successfully!')
            ]
        );
    }

    public function updateOrganization(StoreProfileAddress $request)
    {
        $organizationId = (int) session('current_organization');
        $this->checkPermission('settings.manage', $organizationId);

        $organizationConfig = Organization::where('id', $organizationId)->firstOrFail();
        $companyProfileManagedByParent = $this->organizationUsesInheritedCompanyProfile($organizationConfig);

        if (! $companyProfileManagedByParent
            && ! app(PermissionService::class)->isOwner($organizationId)
            && $this->requestChangesWorkspaceIdentity($request, $organizationConfig)) {
            abort(403, __('Only workspace owners can update workspace identity details.'));
        }

        $metadataArray = $organizationConfig->metadata ? json_decode($organizationConfig->metadata, true) : [];
        $normalizedRetryIntervals = collect((array) $request->input('resend_intervals', []))
            ->map(fn ($interval) => (int) $interval)
            ->filter(fn (int $interval) => $interval > 0)
            ->values()
            ->all();

        $metadataArray['timezone'] = $request->input('timezone');

        $metadataArray['campaigns']['enable_resend'] = $request->input('enable_campaign_resend');
        $metadataArray['campaigns']['move_failed_contacts_to_group'] = $request->input('move_failed_contacts_to_group');
        $metadataArray['campaigns']['resend_intervals'] = $normalizedRetryIntervals;
        $metadataArray['campaigns']['failed_campaign_group'] = $request->boolean('move_failed_contacts_to_group')
            ? $request->input('failed_campaign_group')
            : null;

        $addressArray['street'] = $request->input('address');
        $addressArray['city'] = $request->input('city');
        $addressArray['state'] = $request->input('state');
        $addressArray['zip'] = $request->input('zip');
        $addressArray['country'] = $request->input('country');

        if (! $companyProfileManagedByParent) {
            $organizationConfig->name = $request->input('organization_name');
            $organizationConfig->address = json_encode($addressArray);
        }

        $organizationConfig->metadata = json_encode($metadataArray);

        if($organizationConfig->save()){
            return Redirect::back()->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('Organization updated successfully!')
                ]
            );
        } else {
            return Redirect::back()->with(
                'status', [
                    'type' => 'error', 
                    'message' => __('Something went wrong. Refresh the page and try again')
                ]
            );
        }
    }

    private function requestChangesWorkspaceIdentity(StoreProfileAddress $request, Organization $organization): bool
    {
        $currentAddress = $organization->address ? json_decode($organization->address, true) : [];

        $requestedIdentity = [
            'name' => $this->normalizeWorkspaceIdentityValue($request->input('organization_name')),
            'street' => $this->normalizeWorkspaceIdentityValue($request->input('address')),
            'city' => $this->normalizeWorkspaceIdentityValue($request->input('city')),
            'state' => $this->normalizeWorkspaceIdentityValue($request->input('state')),
            'zip' => $this->normalizeWorkspaceIdentityValue($request->input('zip')),
            'country' => $this->normalizeWorkspaceIdentityValue($request->input('country')),
        ];

        $currentIdentity = [
            'name' => $this->normalizeWorkspaceIdentityValue($organization->name),
            'street' => $this->normalizeWorkspaceIdentityValue($currentAddress['street'] ?? null),
            'city' => $this->normalizeWorkspaceIdentityValue($currentAddress['city'] ?? null),
            'state' => $this->normalizeWorkspaceIdentityValue($currentAddress['state'] ?? null),
            'zip' => $this->normalizeWorkspaceIdentityValue($currentAddress['zip'] ?? null),
            'country' => $this->normalizeWorkspaceIdentityValue($currentAddress['country'] ?? null),
        ];

        return $requestedIdentity !== $currentIdentity;
    }

    private function normalizeWorkspaceIdentityValue($value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function organizationUsesInheritedCompanyProfile(Organization $organization): bool
    {
        $billingOwner = app(OrganizationHierarchyService::class)->billingOwner((int) $organization->id);

        return $organization->organization_type === 'branch'
            && $billingOwner
            && (int) $billingOwner->id !== (int) $organization->id;
    }

}
