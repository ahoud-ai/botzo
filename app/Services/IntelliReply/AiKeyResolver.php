<?php

namespace App\Services\IntelliReply;

use App\Models\Setting;
use App\Services\SubscriptionPlanLimitService;
use Illuminate\Support\Facades\Crypt;

class AiKeyResolver
{
    public const POLICY_GLOBAL_ONLY = 'global_only';
    public const POLICY_ORGANIZATION_ONLY = 'organization_only';
    public const POLICY_HYBRID = 'hybrid';

    public function __construct(
        private readonly SubscriptionPlanLimitService $planLimitService,
    ) {
    }

    /**
     * @return array{policy:string,allow_org_override:bool,has_global_key:bool}
     */
    public function getPolicyConfig(): array
    {
        $policy = (string) (Setting::where('key', 'ai_key_policy')->value('value') ?? self::POLICY_HYBRID);
        if (!in_array($policy, [self::POLICY_GLOBAL_ONLY, self::POLICY_ORGANIZATION_ONLY, self::POLICY_HYBRID], true)) {
            $policy = self::POLICY_HYBRID;
        }

        $allowOrgOverrideRaw = Setting::where('key', 'ai_allow_org_override')->value('value');
        $allowOrgOverride = $allowOrgOverrideRaw === null
            ? true
            : in_array((string) $allowOrgOverrideRaw, ['1', 'true', 'on'], true);

        return [
            'policy' => $policy,
            'allow_org_override' => $allowOrgOverride,
            'has_global_key' => $this->resolveGlobalKey() !== null,
        ];
    }

    /**
     * @return array{key:?string,source:?string,policy:string,allow_org_override:bool,has_global_key:bool,has_org_key:bool}
     */
    public function resolveForOrganization(
        array $organizationMetadata,
        ?string $preferredSource = null,
        ?int $organizationId = null
    ): array
    {
        $config = $this->getPolicyConfig();
        $policy = $config['policy'];
        $allowOrgOverride = $config['allow_org_override'];
        $organizationKeyAllowed = $organizationId === null
            ? true
            : $this->planLimitService->boolForOrganization($organizationId, 'ai_organization_key_enabled', true);

        $orgKey = $organizationKeyAllowed
            ? $this->resolveOrganizationKey($organizationMetadata)
            : null;
        $globalKey = $this->resolveGlobalKey();

        $normalizedPreferred = in_array($preferredSource, ['organization', 'global', 'auto'], true)
            ? $preferredSource
            : 'auto';

        $resolvedKey = null;
        $resolvedSource = null;

        if ($policy === self::POLICY_GLOBAL_ONLY) {
            $resolvedKey = $globalKey;
            $resolvedSource = $globalKey ? 'global' : null;
        } elseif ($policy === self::POLICY_ORGANIZATION_ONLY) {
            $resolvedKey = $orgKey;
            $resolvedSource = $orgKey ? 'organization' : null;
        } else {
            // Hybrid policy
            if ($normalizedPreferred === 'global') {
                if ($globalKey) {
                    $resolvedKey = $globalKey;
                    $resolvedSource = 'global';
                } elseif ($allowOrgOverride && $orgKey) {
                    $resolvedKey = $orgKey;
                    $resolvedSource = 'organization';
                }
            } elseif ($normalizedPreferred === 'organization') {
                if ($allowOrgOverride && $orgKey) {
                    $resolvedKey = $orgKey;
                    $resolvedSource = 'organization';
                } elseif ($globalKey) {
                    $resolvedKey = $globalKey;
                    $resolvedSource = 'global';
                }
            } else {
                // auto
                if ($allowOrgOverride && $orgKey) {
                    $resolvedKey = $orgKey;
                    $resolvedSource = 'organization';
                } elseif ($globalKey) {
                    $resolvedKey = $globalKey;
                    $resolvedSource = 'global';
                }
            }
        }

        return [
            'key' => $resolvedKey,
            'source' => $resolvedSource,
            'policy' => $policy,
            'allow_org_override' => $allowOrgOverride,
            'has_global_key' => $globalKey !== null,
            'has_org_key' => $orgKey !== null,
            'organization_key_allowed' => $organizationKeyAllowed,
        ];
    }

    public function resolveOrganizationKey(array $organizationMetadata): ?string
    {
        $encrypted = data_get($organizationMetadata, 'ai.api_key_encrypted');
        if (is_string($encrypted) && trim($encrypted) !== '') {
            try {
                return Crypt::decryptString($encrypted);
            } catch (\Throwable $e) {
                // Continue to previous key fallback.
            }
        }

        $plain = data_get($organizationMetadata, 'ai.api_key');
        if (is_string($plain) && trim($plain) !== '') {
            return $plain;
        }

        return null;
    }

    public function resolveGlobalKey(): ?string
    {
        $encrypted = Setting::where('key', 'ai_global_api_key_encrypted')->value('value');
        if (is_string($encrypted) && trim($encrypted) !== '') {
            try {
                return Crypt::decryptString($encrypted);
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Transitional fallback for older installations before encrypted key migration.
        $plain = Setting::where('key', 'ai_global_api_key')->value('value');
        if (is_string($plain) && trim($plain) !== '') {
            return $plain;
        }

        return null;
    }
}
