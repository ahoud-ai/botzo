<?php

namespace App\Services\AutomationFlows;

use App\Models\AutomationFlow;
use App\Models\AutomationFlowAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AutomationFlowAssetService
{
    public function store(AutomationFlow $flow, UploadedFile $file, int $userId, ?string $mediaKind = null): AutomationFlowAsset
    {
        $kind = $mediaKind ?: $this->detectKind((string) $file->getMimeType());
        $directory = sprintf('automation-flows/%d/%s', $flow->organization_id, $flow->uuid);
        $storedPath = $file->store($directory, 'local');

        return AutomationFlowAsset::create([
            'uuid' => (string) Str::uuid(),
            'automation_flow_id' => $flow->id,
            'organization_id' => $flow->organization_id,
            'media_kind' => $kind,
            'disk' => 'local',
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'meta_json' => [
                'extension' => $file->getClientOriginalExtension(),
            ],
            'created_by' => $userId,
        ]);
    }

    public function delete(AutomationFlow $flow, AutomationFlowAsset $asset): void
    {
        abort_if($asset->automation_flow_id !== $flow->id, 404);

        if ($asset->disk && $asset->path && Storage::disk($asset->disk)->exists($asset->path)) {
            Storage::disk($asset->disk)->delete($asset->path);
        }

        $asset->delete();
    }

    public function downloadPayload(AutomationFlow $flow, AutomationFlowAsset $asset): array
    {
        abort_if($asset->automation_flow_id !== $flow->id, 404);
        abort_if(! $asset->path || ! Storage::disk($asset->disk)->exists($asset->path), 404);

        return [
            'content' => Storage::disk($asset->disk)->get($asset->path),
            'mime_type' => $asset->mime_type ?: 'application/octet-stream',
            'filename' => $asset->original_name ?: basename($asset->path),
        ];
    }

    public function publicUrl(AutomationFlow $flow, AutomationFlowAsset $asset): string
    {
        return URL::temporarySignedRoute(
            'flowbuilder.assets.show',
            now()->addMinutes((int) config('automation_flows.asset_url_ttl_minutes', 1440)),
            [
                'uuid' => $flow->uuid,
                'assetUuid' => $asset->uuid,
            ]
        );
    }

    public function toBuilderArray(AutomationFlow $flow, AutomationFlowAsset $asset): array
    {
        return [
            'uuid' => $asset->uuid,
            'media_kind' => $asset->media_kind,
            'original_name' => $asset->original_name,
            'mime_type' => $asset->mime_type,
            'size' => (int) $asset->size,
            'url' => $this->publicUrl($flow, $asset),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function duplicateAll(AutomationFlow $sourceFlow, AutomationFlow $targetFlow, int $userId): array
    {
        $sourceFlow->loadMissing('assets');

        return $sourceFlow->assets
            ->mapWithKeys(function (AutomationFlowAsset $asset) use ($targetFlow, $userId): array {
                $newUuid = (string) Str::uuid();
                $newPath = $this->duplicatePath($targetFlow, $asset, $newUuid);

                if ($asset->path && Storage::disk($asset->disk)->exists($asset->path)) {
                    Storage::disk($asset->disk)->copy($asset->path, $newPath);
                }

                AutomationFlowAsset::create([
                    'uuid' => $newUuid,
                    'automation_flow_id' => $targetFlow->id,
                    'organization_id' => $targetFlow->organization_id,
                    'media_kind' => $asset->media_kind,
                    'disk' => $asset->disk,
                    'path' => $newPath,
                    'original_name' => $asset->original_name,
                    'mime_type' => $asset->mime_type,
                    'size' => (int) $asset->size,
                    'meta_json' => $asset->meta_json,
                    'created_by' => $userId,
                ]);

                return [$asset->uuid => $newUuid];
            })
            ->all();
    }

    public function detectKind(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        return 'document';
    }

    private function duplicatePath(AutomationFlow $flow, AutomationFlowAsset $asset, string $newUuid): string
    {
        $directory = sprintf('automation-flows/%d/%s', $flow->organization_id, $flow->uuid);
        $extension = pathinfo((string) $asset->path, PATHINFO_EXTENSION);

        return $directory.'/'.$newUuid.($extension ? '.'.$extension : '');
    }
}
