<?php

namespace App\Http\Resources;

use App\Helpers\DateTimeHelper;
use App\Services\FeatureSetupPolicyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $policy = app(FeatureSetupPolicyService::class)->getPolicy($this->resource);

        $data['updated_at'] = DateTimeHelper::formatDate($this->updated_at);
        $data['feature_setup_supported'] = (bool) ($policy['setup_supported'] ?? false);
        $data['coming_soon'] = (bool) ($policy['coming_soon'] ?? false);
        $data['setup_reason'] = $policy['reason'] ?? null;
        $data['resolved_input_fields'] = $this->resolveInputFields($data, $policy);

        return $data;
    }

    private function resolveInputFields(array $data, array $policy): array
    {
        $metadata = $this->decodeMetadata($data['metadata'] ?? null);
        $metadataInputFields = $this->normalizeInputFields($metadata['input_fields'] ?? null);

        if (!empty($metadataInputFields)) {
            return $metadataInputFields;
        }

        if (($data['name'] ?? null) === 'AI Assistant') {
            $fallbackFields = data_get($policy, 'setup_defaults.metadata.input_fields', []);

            return $this->normalizeInputFields($fallbackFields);
        }

        return [];
    }

    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (!is_string($metadata) || trim($metadata) === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeInputFields(mixed $inputFields): array
    {
        if (!is_array($inputFields)) {
            return [];
        }

        return array_values(array_filter($inputFields, static function ($field): bool {
            return is_array($field) && !empty($field['name']);
        }));
    }
}
