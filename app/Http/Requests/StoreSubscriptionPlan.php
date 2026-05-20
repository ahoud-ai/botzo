<?php

namespace App\Http\Requests;

use App\Models\Addon;
use App\Support\SaClientPlanProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSubscriptionPlan extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'nullable|string|max:100|required_without_all:name_ar,name_en',
            'name_ar' => 'nullable|string|max:100|required_without_all:name,name_en',
            'name_en' => 'nullable|string|max:100|required_without_all:name,name_ar',
            'price' => 'required|numeric|gte:0',
            'period' => 'required|string|in:monthly,yearly',
            'tier_rank' => 'required|integer|min:1',
            'campaign_limit' => 'required|numeric|gte:-1',
            'message_limit' => 'required|numeric|gte:-1',
            'contacts_limit' => 'required|numeric|gte:-1',
            'canned_replies_limit' => 'required|numeric|gte:-1',
            'team_limit' => 'required|numeric|gte:-1',
            'ai_text_response_limit' => 'nullable|numeric|gte:-1',
            'ai_audio_response_limit' => 'nullable|numeric|gte:-1',
            'ai_organization_key_enabled' => 'required|boolean',
            'branches_limit' => 'required|numeric|gte:-1',
            'ai_system_key_monthly_quota' => 'required|numeric|gte:-1',
            'flow_builder_active_flows_limit' => 'required|numeric|gte:-1',
            'flow_builder_nodes_per_flow_limit' => 'required|numeric|gte:-1',
            'flow_builder_monthly_runs_limit' => 'required|numeric|gte:-1',
            'flow_builder_advanced_enabled' => 'required|boolean',
            'receive_messages_after_expiration' => 'required|boolean',
            'addons' => 'nullable|array',
            'addons.*' => 'boolean',
            'custom_features' => 'nullable|array',
            'custom_features.*.text_ar' => 'nullable|string|max:255|required_with:custom_features.*.text_en',
            'custom_features.*.text_en' => 'nullable|string|max:255|required_with:custom_features.*.text_ar',
            'status' => 'required|string|in:active,inactive',
        ];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $addons = $this->input('addons', []);
            if (!is_array($addons) || $addons === []) {
                return;
            }

            $allowedAddons = Addon::query()
                ->where('status', 1)
                ->where('is_plan_restricted', 1)
                ->whereIn('name', SaClientPlanProfile::planAddonNames())
                ->pluck('name')
                ->all();

            foreach (array_keys($addons) as $addonName) {
                $normalizedName = trim((string) $addonName);
                if ($normalizedName === '' || !in_array($normalizedName, $allowedAddons, true)) {
                    $validator->errors()->add(
                        'addons',
                        __('The selected addon is not available for subscription plans: :addon', [
                            'addon' => $normalizedName !== '' ? $normalizedName : __('Unnamed addon'),
                        ])
                    );
                }
            }
        });
    }
}
