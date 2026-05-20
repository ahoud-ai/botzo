<?php

namespace App\Services;

use App\Models\OrganizationApiKey;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrganizationApiService
{
    public function __construct(private OrganizationApiTokenHasher $tokenHasher)
    {
    }

    public function generate(?int $organizationId = null): array
    {
        $organizationId = $organizationId ?? (int) session()->get('current_organization');
        $plainTextToken = $this->tokenHasher->generatePlainTextToken();

        $apiKey = OrganizationApiKey::create([
            'organization_id' => $organizationId,
            'token' => $this->tokenHasher->hashToken($plainTextToken),
            'token_last_four' => $this->tokenHasher->lastFour($plainTextToken),
        ]);

        return [
            'token' => $plainTextToken,
            'record' => $apiKey,
        ];
    }

    public function rotate(string $uuid, ?int $organizationId = null): array
    {
        $organizationId = $organizationId ?? (int) session()->get('current_organization');
        $apiKey = OrganizationApiKey::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->first();

        if (!$apiKey) {
            throw new ModelNotFoundException(__('Organization API key not found.'));
        }

        $plainTextToken = $this->tokenHasher->generatePlainTextToken();
        $apiKey->update([
            'token' => $this->tokenHasher->hashToken($plainTextToken),
            'token_last_four' => $this->tokenHasher->lastFour($plainTextToken),
            'deleted_at' => null,
            'deleted_by' => null,
        ]);

        return [
            'token' => $plainTextToken,
            'record' => $apiKey->fresh(),
        ];
    }

    public function destroy($uuid): void
    {
        OrganizationApiKey::where('uuid', $uuid)
            ->where('organization_id', session()->get('current_organization'))
            ->update([
            'deleted_at' => now(),
            'deleted_by' => auth()->id(),
        ]);
    }

    public function findActiveTokenRecord(string $plainTextToken): ?OrganizationApiKey
    {
        $hashedToken = $this->tokenHasher->hashToken($plainTextToken);

        return OrganizationApiKey::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($hashedToken, $plainTextToken) {
                $query->where('token', $hashedToken)
                    ->orWhere('token', $plainTextToken);
            })
            ->first();
    }

    public function recordUsage(OrganizationApiKey $apiKey): void
    {
        if ($apiKey->last_used_at && $apiKey->last_used_at->gt(now()->subMinutes(5))) {
            return;
        }

        $apiKey->forceFill([
            'last_used_at' => now(),
        ])->saveQuietly();
    }
}
