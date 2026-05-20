<?php

namespace App\Http\Middleware;

use App\Models\Chat;
use App\Models\ChatTicket;
use App\Models\Language;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Services\OrganizationHierarchyService;
use App\Services\OrganizationSessionService;
use App\Services\OrganizationService;
use App\Services\AutomationFlows\AutomationFlowAccessService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private const ADMIN_COMPANION_MODULE_ACTIONS = [
        'settings.general' => [
            'languages' => ['view', 'create', 'edit', 'delete'],
        ],
        'customers.view' => [
            'logs' => ['view'],
        ],
    ];

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        $manifestVersion = $this->assetManifestVersion();

        if ($manifestVersion === null) {
            return parent::version($request);
        }

        $assetUrl = trim((string) config('app.asset_url', ''));
        $versionParts = $assetUrl !== ''
            ? [$assetUrl, $manifestVersion]
            : [$manifestVersion];

        return hash('xxh128', implode('|', $versionParts));
    }

    private function assetManifestVersion(): ?string
    {
        foreach ([public_path('build/manifest.json'), public_path('mix-manifest.json')] as $manifest) {
            if (! is_file($manifest)) {
                continue;
            }

            $hash = hash_file('xxh128', $manifest);

            return $hash === false ? null : $hash;
        }

        return null;
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $isPublicFrontendRequest = $this->isPublicFrontendRequest($request);
        $supportedLocales = $this->supportedLocales();
        $language = $this->resolveActiveLocale($request, $user);

        $sharedSettingsPayload = null;
        $resolveSharedSettingsPayload = function () use (&$sharedSettingsPayload, $isPublicFrontendRequest, $supportedLocales, $language) {
            if ($sharedSettingsPayload === null) {
                $sharedSettingsPayload = $this->resolveSharedSettingsPayload(
                    $isPublicFrontendRequest,
                    $supportedLocales,
                    $language
                );
            }

            return $sharedSettingsPayload;
        };

        $workspacePayload = null;
        $resolveWorkspacePayload = function () use (&$workspacePayload, $request, $user) {
            if ($workspacePayload === null) {
                $workspacePayload = $this->resolveWorkspacePayload($request, $user);
            }

            return $workspacePayload;
        };

        return array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),
            'config' => fn () => $resolveSharedSettingsPayload()['config'],
            'phoneCountries' => $isPublicFrontendRequest ? [] : config('formats.phone_countries', []),
            'auth' => fn () => [
                'user' => $resolveWorkspacePayload()['authUser'],
            ],
            'organization' => fn () => $resolveWorkspacePayload()['organization'],
            'organizations' => fn () => $resolveWorkspacePayload()['organizations'],
            'branchCreationContext' => fn () => $resolveWorkspacePayload()['branchCreationContext'],
            'workspaceAccess' => fn () => $resolveWorkspacePayload()['workspaceAccess'],
            'permissions' => fn () => $resolveWorkspacePayload()['permissions'],
            'adminPermissions' => fn () => $this->resolveAdminPermissions($request),
            'flash' => [
                'status'=> session('status'),
                'generated_api_token' => session('generated_api_token'),
                'generated_api_token_action' => session('generated_api_token_action'),
            ],
            'refresh_lang' => session('refresh_lang', false),
            'response_data' => fn () => $request->session()->get('response_data'),
            'languages' => fn () => $resolveSharedSettingsPayload()['languages'],
            'unreadMessages' => fn () => $resolveWorkspacePayload()['unreadMessages'],
            'currentLanguage' => $language,
            'features' => fn () => [
                'flowBuilderV2' => $resolveWorkspacePayload()['flowBuilderV2Available'],
            ],
            'isRtl' => fn () => $resolveSharedSettingsPayload()['isRtl'],
        ]);
    }

    private function resolveActiveLocale(Request $request, ?User $user): string
    {
        if ($user && $user->language) {
            $locale = $this->normalizeLocale($user->language);
            app()->setLocale($locale);

            return $locale;
        }

        $sessionLocale = $request->session()->get('locale');
        if (is_string($sessionLocale) && $sessionLocale !== '') {
            $locale = $this->normalizeLocale($sessionLocale);
            app()->setLocale($locale);

            return $locale;
        }

        $locale = $this->normalizeLocale(app()->getLocale());
        app()->setLocale($locale);

        return $locale;
    }

    private function resolveSharedSettingsPayload(
        bool $isPublicFrontendRequest,
        array $supportedLocales,
        string $language
    ): array {
        if ($this->isBootstrapped()) {
            try {
                $keys = $this->sharedConfigKeys($isPublicFrontendRequest);
                $config = Setting::whereIn('key', $keys)->get();
                $languages = Language::whereNull('deleted_at')
                    ->where('status', 'active')
                    ->get()
                    ->filter(function ($item) use ($supportedLocales) {
                        return in_array(strtolower((string) $item->code), $supportedLocales, true);
                    })
                    ->map(function ($item) {
                        return [
                            'id' => (int) $item->id,
                            'name' => (string) $item->name,
                            'code' => strtolower((string) $item->code),
                            'status' => (string) $item->status,
                            'is_rtl' => (bool) $item->is_rtl,
                        ];
                    })
                    ->values();

                if ($languages->isEmpty()) {
                    $languages = collect([
                        ['id' => 0, 'code' => 'en', 'name' => 'English', 'is_rtl' => false, 'status' => 'active'],
                        ['id' => 0, 'code' => 'ar', 'name' => 'Arabic', 'is_rtl' => true, 'status' => 'active'],
                    ]);
                }

                $currentLanguage = $languages->firstWhere('code', $language);
                $isRtl = is_array($currentLanguage)
                    ? (bool) ($currentLanguage['is_rtl'] ?? false)
                    : $language === 'ar';

                return [
                    'config' => $config,
                    'languages' => $languages,
                    'isRtl' => $isRtl,
                ];
            } catch (\Throwable) {
                // Keep the app responsive even if optional shared settings fail.
            }
        }

        return [
            'config' => [],
            'languages' => collect([
                ['id' => 0, 'code' => 'en', 'name' => 'English', 'is_rtl' => false, 'status' => 'active'],
                ['id' => 0, 'code' => 'ar', 'name' => 'Arabic', 'is_rtl' => true, 'status' => 'active'],
            ]),
            'isRtl' => $language === 'ar',
        ];
    }

    private function resolveWorkspacePayload(Request $request, ?User $user): array
    {
        $organization = [];
        $organizations = [];
        $permissions = [];
        $branchCreationContext = null;
        $workspaceAccess = $this->emptyWorkspaceAccess();
        $unreadMessages = 0;
        $flowBuilderV2Available = false;
        $authUser = $user ?: null;

        if (! $user) {
            return [
                'authUser' => $authUser,
                'organization' => $organization,
                'organizations' => $organizations,
                'branchCreationContext' => $branchCreationContext,
                'workspaceAccess' => $workspaceAccess,
                'permissions' => $permissions,
                'unreadMessages' => $unreadMessages,
                'flowBuilderV2Available' => $flowBuilderV2Available,
            ];
        }

        $organizationId = session('current_organization');
        $organizationSessionService = app(OrganizationSessionService::class);
        $accessibleOrganizationIds = $organizationSessionService->accessibleOrganizationIdsForUser($user->id);
        $directOrganizationIds = $organizationSessionService->organizationIdsForUser($user->id);
        $organizations = $this->buildOrganizationSwitcherRows(
            $accessibleOrganizationIds,
            $directOrganizationIds,
            $organizationId ? (int) $organizationId : null
        );

        if ($organizationId) {
            $directTeam = Team::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->with('organizationRole')
                ->first();

            $authUser->setRelation('teams', $directTeam ? collect([$directTeam]) : collect());

            $organization = Organization::with('parentOrganization')
                ->where('id', $organizationId)
                ->whereNull('deleted_at')
                ->first();
            $flowBuilderV2Available = app(AutomationFlowAccessService::class)
                ->surfaceAvailableForOrganization((int) $organizationId);

            $permissionService = new PermissionService();

            try {
                $billingOwner = app(OrganizationHierarchyService::class)->billingOwner((int) $organizationId);
                $branchCreationGuard = app(OrganizationService::class)->branchCreationContextForParent($billingOwner);

                $branchCreationContext = $billingOwner ? [
                    'type' => 'branch',
                    'parentOrganization' => [
                        'uuid' => $billingOwner->uuid,
                        'name' => $billingOwner->name,
                    ],
                    'canCreateBranch' => (bool) ($branchCreationGuard['can_create_branch'] ?? true),
                    'blockingCode' => $branchCreationGuard['blocking_code'] ?? null,
                    'blockingMessage' => $branchCreationGuard['blocking_message'] ?? null,
                    'limitSnapshot' => $branchCreationGuard['limit_snapshot'] ?? null,
                ] : null;
            } catch (\Throwable) {
                $billingOwner = null;
                $branchCreationContext = null;
            }

            try {
                $permissions = $permissionService->getPermissions($organizationId);
                if (! is_array($permissions)) {
                    $permissions = [];
                }
            } catch (\Throwable) {
                $permissions = [];
            }

            $workspaceAccess = $this->buildWorkspaceAccessPayload(
                $organization,
                $directTeam,
                $permissionService,
                $billingOwner ?? null
            );

            $unreadMessages = $this->resolveUnreadMessages(
                $organization,
                (int) $organizationId,
                $permissionService,
                (int) $user->id
            );
        } else {
            $authUser->setRelation('teams', collect());
        }

        return [
            'authUser' => $authUser,
            'organization' => $organization,
            'organizations' => $organizations,
            'branchCreationContext' => $branchCreationContext,
            'workspaceAccess' => $workspaceAccess,
            'permissions' => $permissions,
            'unreadMessages' => $unreadMessages,
            'flowBuilderV2Available' => $flowBuilderV2Available,
        ];
    }

    private function resolveUnreadMessages(
        ?Organization $organization,
        int $organizationId,
        PermissionService $permissionService,
        int $userId
    ): int {
        $ticketingActive = false;
        if ($organization && $organization->metadata) {
            $settings = json_decode($organization->metadata, true);
            if (
                isset($settings['tickets'])
                && isset($settings['tickets']['active'])
                && $settings['tickets']['active'] === true
            ) {
                $ticketingActive = true;
            }
        }

        if ($permissionService->isOwner($organizationId)) {
            return Chat::where('organization_id', $organizationId)
                ->where('type', 'inbound')
                ->whereNull('deleted_at')
                ->where('is_read', 0)
                ->count();
        }

        if ($ticketingActive) {
            $assignedContactIds = ChatTicket::where('assigned_to', $userId)
                ->pluck('contact_id')
                ->toArray();

            if ($assignedContactIds === []) {
                return 0;
            }

            return Chat::where('organization_id', $organizationId)
                ->where('type', 'inbound')
                ->whereNull('deleted_at')
                ->where('is_read', 0)
                ->whereIn('contact_id', $assignedContactIds)
                ->count();
        }

        return Chat::where('organization_id', $organizationId)
            ->where('type', 'inbound')
            ->whereNull('deleted_at')
            ->where('is_read', 0)
            ->count();
    }

    private function isPublicFrontendRequest(Request $request): bool
    {
        $normalizedPath = trim((string) $request->path(), '/');

        if ($normalizedPath === '') {
            return true;
        }

        $publicPrefixes = collect(config('frontend.seo_public_path_prefixes', ['pages/']))
            ->map(static fn ($prefix) => trim((string) $prefix, '/').'/')
            ->filter(static fn ($prefix) => $prefix !== '/')
            ->unique()
            ->values()
            ->all();

        if (Str::startsWith($normalizedPath, $publicPrefixes)) {
            return true;
        }

        $publicPaths = collect(config('frontend.seo_public_paths', []))
            ->map(static fn ($path) => trim((string) $path, '/'))
            ->unique()
            ->values()
            ->all();

        return in_array($normalizedPath, $publicPaths, true);
    }

    private function sharedConfigKeys(bool $isPublicFrontendRequest): array
    {
        if ($isPublicFrontendRequest) {
            return [
                'favicon',
                'logo',
                'company_name',
                'head_scripts',
                'head_styles',
                'body_scripts',
                'meta_tags',
                'primary_color',
                'secondary_color',
                'seo_site_name_ar',
                'seo_site_name_en',
                'seo_home_title_ar',
                'seo_home_title_en',
                'seo_default_title_ar',
                'seo_default_title_en',
                'seo_home_description_ar',
                'seo_home_description_en',
                'seo_default_description_ar',
                'seo_default_description_en',
                'seo_keywords_ar',
                'seo_keywords_en',
                'seo_og_title_ar',
                'seo_og_title_en',
                'seo_og_description_ar',
                'seo_og_description_en',
                'seo_share_image',
                'seo_twitter_card',
                'seo_twitter_site',
                'seo_canonical_base_url',
                'seo_google_verification',
                'seo_bing_verification',
                'seo_robots_index',
                'seo_robots_follow',
                'seo_robots_custom',
                'tracking_meta_pixel_id',
                'tracking_tiktok_pixel_id',
            ];
        }

        return [
            'favicon',
            'logo',
            'company_name',
            'address',
            'currency',
            'email',
            'phone',
            'socials',
            'trial_period',
            'pusher_app_key',
            'pusher_app_cluster',
            'enable_api_key_input',
            'enable_model_selection',
            'default_open_ai_text_model',
            'default_open_ai_audio_model',
            'head_scripts',
            'head_styles',
            'body_scripts',
            'meta_tags',
            'primary_color',
            'secondary_color',
            'seo_site_name_ar',
            'seo_site_name_en',
            'seo_home_title_ar',
            'seo_home_title_en',
            'seo_default_title_ar',
            'seo_default_title_en',
            'seo_home_description_ar',
            'seo_home_description_en',
            'seo_default_description_ar',
            'seo_default_description_en',
            'seo_keywords_ar',
            'seo_keywords_en',
            'seo_og_title_ar',
            'seo_og_title_en',
            'seo_og_description_ar',
            'seo_og_description_en',
            'seo_share_image',
            'seo_twitter_card',
            'seo_twitter_site',
            'seo_canonical_base_url',
            'seo_google_verification',
            'seo_bing_verification',
            'seo_robots_index',
            'seo_robots_follow',
            'seo_robots_custom',
            'tracking_meta_pixel_id',
            'tracking_tiktok_pixel_id',
        ];
    }

    /**
     * Checks if the application has been installed.
     *
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return file_exists(storage_path('in'.'stalled'));
    }

    private function normalizeLocale(?string $locale): string
    {
        $normalized = strtolower((string) $locale);

        if (!in_array($normalized, $this->supportedLocales(), true)) {
            return 'en';
        }

        return $normalized;
    }

    private function supportedLocales(): array
    {
        $supported = config('i18n.supported_locales', ['en', 'ar']);

        return array_values(array_unique(array_map(static fn ($locale) => strtolower((string) $locale), $supported)));
    }

    private function resolveAdminPermissions(Request $request): array
    {
        $basePayload = [
            'enabled' => false,
            'role' => null,
            'isSuperAdmin' => false,
            'moduleActions' => [],
        ];

        $adminUser = Auth::guard('admin')->user();

        if (! $adminUser) {
            return $basePayload;
        }

        $payload = $basePayload;
        $payload['enabled'] = true;
        $payload['role'] = (string) $adminUser->role;

        $normalizedRole = strtolower((string) $adminUser->role);

        if (in_array($normalizedRole, ['admin', 'owner'], true)) {
            $payload['isSuperAdmin'] = true;

            return $payload;
        }

        try {
            $role = Role::query()
                ->whereRaw('LOWER(name) = ?', [$normalizedRole])
                ->whereNull('deleted_at')
                ->first();

            if (! $role) {
                return $payload;
            }

            $moduleActions = [];

            $permissions = RolePermission::query()
                ->where('role_id', $role->id)
                ->get(['module', 'action']);

            foreach ($permissions as $permission) {
                $moduleName = strtolower(trim((string) $permission->module));
                $actionName = strtolower(trim((string) $permission->action));

                if ($moduleName === '' || $actionName === '') {
                    continue;
                }

                if (! isset($moduleActions[$moduleName])) {
                    $moduleActions[$moduleName] = [];
                }

                if (! in_array($actionName, $moduleActions[$moduleName], true)) {
                    $moduleActions[$moduleName][] = $actionName;
                }
            }

            $moduleActions = $this->appendAdminCompanionModules($moduleActions);

            foreach ($moduleActions as &$actions) {
                sort($actions);
            }
            unset($actions);

            ksort($moduleActions);
            $payload['moduleActions'] = $moduleActions;
        } catch (\Throwable) {
            $payload['moduleActions'] = [];
        }

        return $payload;
    }

    private function appendAdminCompanionModules(array $moduleActions): array
    {
        foreach (self::ADMIN_COMPANION_MODULE_ACTIONS as $sourcePermission => $targets) {
            [$sourceModule, $sourceAction] = array_pad(explode('.', $sourcePermission, 2), 2, null);

            if (! is_string($sourceModule) || ! is_string($sourceAction)) {
                continue;
            }

            if (! isset($moduleActions[$sourceModule]) || ! in_array($sourceAction, $moduleActions[$sourceModule], true)) {
                continue;
            }

            foreach ($targets as $targetModule => $targetActions) {
                if (! isset($moduleActions[$targetModule])) {
                    $moduleActions[$targetModule] = [];
                }

                foreach ((array) $targetActions as $targetAction) {
                    $normalizedTargetAction = strtolower(trim((string) $targetAction));
                    if ($normalizedTargetAction === '') {
                        continue;
                    }

                    if (! in_array($normalizedTargetAction, $moduleActions[$targetModule], true)) {
                        $moduleActions[$targetModule][] = $normalizedTargetAction;
                    }
                }
            }
        }

        return $moduleActions;
    }

    private function emptyWorkspaceAccess(): array
    {
        return [
            'isOwner' => false,
            'hasDirectMembership' => false,
            'authoritySource' => 'none',
            'canManageTeam' => false,
            'canManageRoles' => false,
            'canManageCompanyProfile' => false,
            'canViewBilling' => false,
            'isBranch' => false,
            'subscriptionManagedByParent' => false,
            'companyProfileManagedByParent' => false,
            'billingOwner' => null,
        ];
    }

    private function buildWorkspaceAccessPayload(
        ?Organization $organization,
        ?Team $directTeam,
        PermissionService $permissionService,
        ?Organization $billingOwner
    ): array {
        if (! $organization) {
            return $this->emptyWorkspaceAccess();
        }

        $organizationId = (int) $organization->id;
        $isOwner = $permissionService->isOwner($organizationId);
        $hasDirectMembership = $directTeam !== null;
        $isBranch = $organization->organization_type === 'branch';
        $subscriptionManagedByParent = $isBranch
            && $billingOwner
            && (int) $billingOwner->id !== $organizationId;
        $companyProfileManagedByParent = $subscriptionManagedByParent;

        return [
            'isOwner' => $isOwner,
            'hasDirectMembership' => $hasDirectMembership,
            'authoritySource' => $isOwner && ! $hasDirectMembership ? 'inherited_parent_owner' : 'direct',
            'canManageTeam' => $isOwner && ! $companyProfileManagedByParent,
            'canManageRoles' => $isOwner && ! $companyProfileManagedByParent,
            'canManageCompanyProfile' => $isOwner && ! $companyProfileManagedByParent,
            'canViewBilling' => $permissionService->can('settings.billing_subscription', $organizationId),
            'isBranch' => $isBranch,
            'subscriptionManagedByParent' => $subscriptionManagedByParent,
            'companyProfileManagedByParent' => $companyProfileManagedByParent,
            'billingOwner' => $billingOwner ? [
                'uuid' => $billingOwner->uuid,
                'name' => $billingOwner->name,
            ] : null,
        ];
    }

    private function buildOrganizationSwitcherRows(
        array $accessibleOrganizationIds,
        array $directOrganizationIds,
        ?int $currentOrganizationId = null
    ): array {
        if ($accessibleOrganizationIds === []) {
            return [];
        }

        return Organization::query()
            ->with('parentOrganization')
            ->whereIn('id', $accessibleOrganizationIds)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->map(function (Organization $organization) use ($directOrganizationIds, $currentOrganizationId) {
                $accessSource = in_array((int) $organization->id, $directOrganizationIds, true)
                    ? 'direct'
                    : 'inherited_parent_owner';

                return [
                    'organization' => [
                        'uuid' => $organization->uuid,
                        'name' => $organization->name,
                        'organization_type' => $organization->organization_type,
                        'parent_organization' => $organization->parentOrganization ? [
                            'uuid' => $organization->parentOrganization->uuid,
                            'name' => $organization->parentOrganization->name,
                        ] : null,
                    ],
                    'access' => [
                        'source' => $accessSource,
                        'label' => $accessSource === 'inherited_parent_owner'
                            ? __('Managed from parent organization')
                            : __('Direct workspace member'),
                        'isCurrent' => $currentOrganizationId !== null
                            && (int) $organization->id === $currentOrganizationId,
                    ],
                ];
            })
            ->values()
            ->all();
    }
}
