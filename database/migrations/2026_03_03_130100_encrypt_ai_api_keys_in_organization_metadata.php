<?php

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Organization::query()
            ->select(['id', 'metadata'])
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(100, function ($organizations): void {
                foreach ($organizations as $organization) {
                    $metadata = json_decode($organization->metadata, true);
                    if (!is_array($metadata) || !isset($metadata['ai']) || !is_array($metadata['ai'])) {
                        continue;
                    }

                    $plainApiKey = $metadata['ai']['api_key'] ?? null;
                    $encryptedApiKey = $metadata['ai']['api_key_encrypted'] ?? null;
                    $hasChanges = false;

                    if (empty($encryptedApiKey) && is_string($plainApiKey) && trim($plainApiKey) !== '') {
                        $metadata['ai']['api_key_encrypted'] = Crypt::encryptString($plainApiKey);
                        $hasChanges = true;
                    }

                    if (array_key_exists('api_key', $metadata['ai'])) {
                        unset($metadata['ai']['api_key']);
                        $hasChanges = true;
                    }

                    if (!$hasChanges) {
                        continue;
                    }

                    Organization::where('id', $organization->id)->update([
                        'metadata' => json_encode($metadata),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep encrypted values in place on rollback to avoid exposing plaintext secrets.
    }
};
