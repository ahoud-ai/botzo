<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Addon;
use App\Services\EmbeddedSignup\EmbeddedSignupService;
use App\Support\SaClientPlanProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AddonController extends BaseController
{
    public function store(Request $request)
    {
        $expectedFeature = (string) $request->route('feature', '');
        $addon = isset($request->uuid) ? Addon::where('uuid', $request->uuid)->first() : null;

        if (!$addon && $expectedFeature !== '') {
            $addon = Addon::query()->where('name', $expectedFeature)->first();
        }

        if (!$addon || !SaClientPlanProfile::isPlanAddon($addon->name) || ($expectedFeature !== '' && $addon->name !== $expectedFeature)) {
            return redirect()->back()->with('status', [
                'type' => 'error',
                'message' => __('This feature is not available in this client version.'),
            ]);
        }

        $settings = $this->filterFeatureSettings($addon->name, is_array($request->settings) ? $request->settings : []);

        if ($addon->name === 'AI Assistant') {
            $settings = $this->normalizeAiAssistantSettings($settings);
        }

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key],['value' => $value]);
        }

        // Enforce encrypted-only storage contract for AI global key.
        if ($addon->name === 'AI Assistant') {
            DB::table('settings')->where('key', 'ai_global_api_key')->delete();
        }

        if (isset($request->is_active)) {
            $requestedState = (int) $request->is_active === 1 ? 1 : 0;

            $addon->update(['is_active' => $requestedState]);
        }

        // Keep embedded signup activation state in a dedicated setting key for user settings checks.
        if ($addon->name === 'Embedded Signup') {
            if ((int) $request->is_active === 1 && !$this->hasEmbeddedSignupAdminConfig($settings)) {
                $addon->update(['is_active' => 0]);
                DB::table('settings')->updateOrInsert(
                    ['key' => 'is_embedded_signup_active'],
                    ['value' => 0]
                );

                return redirect()->back()->with('status', [
                    'type' => 'error',
                    'message' => __('Complete embedded signup setup before enabling feature.'),
                ]);
            }

            DB::table('settings')->updateOrInsert(
                ['key' => 'is_embedded_signup_active'],
                ['value' => $addon->is_active ? 1 : 0]
            );
        }

        if ($addon->name === 'AI Assistant') {
            if ((int) $request->is_active === 1 && !$this->hasAiAssistantAdminConfigForActivation($settings)) {
                $addon->update(['is_active' => 0]);

                return redirect()->back()->with('status', [
                    'type' => 'error',
                    'message' => __('Configure a global OpenAI API key before enabling global-only AI policy.'),
                ]);
            }
        }

        return redirect()->back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('Feature updated successfully!')
            ]
        );
    }

    private function filterFeatureSettings(string $featureName, array $incomingSettings): array
    {
        $allowedKeys = [
            'Embedded Signup' => [
                'whatsapp_client_id',
                'whatsapp_client_secret',
                'whatsapp_config_id',
                'whatsapp_access_token',
            ],
            'AI Assistant' => [
                'ai_global_api_key',
                'ai_key_policy',
                'ai_allow_org_override',
            ],
            'Flow builder' => [],
        ];

        return array_intersect_key(
            $incomingSettings,
            array_flip($allowedKeys[$featureName] ?? [])
        );
    }

    private function hasEmbeddedSignupAdminConfig(array $incomingSettings): bool
    {
        $requiredKeys = [
            'whatsapp_client_id',
            'whatsapp_client_secret',
            'whatsapp_config_id',
        ];

        foreach ($requiredKeys as $key) {
            $value = array_key_exists($key, $incomingSettings)
                ? $incomingSettings[$key]
                : DB::table('settings')->where('key', $key)->value('value');

            if (!is_string($value)) {
                if (empty($value)) {
                    return false;
                }

                continue;
            }

            if (trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeAiAssistantSettings(array $incomingSettings): array
    {
        $settings = $incomingSettings;

        $policy = (string) ($settings['ai_key_policy'] ?? DB::table('settings')->where('key', 'ai_key_policy')->value('value') ?? 'hybrid');
        if (!in_array($policy, ['hybrid', 'global_only', 'organization_only'], true)) {
            $policy = 'hybrid';
        }
        $settings['ai_key_policy'] = $policy;

        $allowOverrideRaw = $settings['ai_allow_org_override']
            ?? DB::table('settings')->where('key', 'ai_allow_org_override')->value('value')
            ?? 1;
        $settings['ai_allow_org_override'] = in_array((string) $allowOverrideRaw, ['1', 'true', 'on'], true) ? 1 : 0;

        $globalKeyInput = trim((string) ($settings['ai_global_api_key'] ?? ''));
        unset($settings['ai_global_api_key']);
        unset($settings['ai_global_api_key_encrypted']);

        if ($globalKeyInput !== '') {
            $settings['ai_global_api_key_encrypted'] = Crypt::encryptString($globalKeyInput);
        }

        return $settings;
    }

    private function hasAiAssistantAdminConfigForActivation(array $incomingSettings): bool
    {
        $policy = (string) ($incomingSettings['ai_key_policy']
            ?? DB::table('settings')->where('key', 'ai_key_policy')->value('value')
            ?? 'hybrid');

        if ($policy !== 'global_only') {
            return true;
        }

        $incomingEncrypted = $incomingSettings['ai_global_api_key_encrypted'] ?? null;
        if (is_string($incomingEncrypted) && trim($incomingEncrypted) !== '') {
            return true;
        }

        $storedEncrypted = DB::table('settings')->where('key', 'ai_global_api_key_encrypted')->value('value');
        if (is_string($storedEncrypted) && trim($storedEncrypted) !== '') {
            return true;
        }

        $storedPlain = DB::table('settings')->where('key', 'ai_global_api_key')->value('value');
        return is_string($storedPlain) && trim($storedPlain) !== '';
    }

    public function embeddedSignupHealth()
    {
        $health = (new EmbeddedSignupService())->buildHealthReport();

        return response()->json($health);
    }

}
