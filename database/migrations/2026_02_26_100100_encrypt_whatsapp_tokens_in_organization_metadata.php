<?php

use App\Models\Organization;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $vault = new WhatsappTokenVault();

        Organization::query()
            ->select(['id', 'metadata'])
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(100, function ($organizations) use ($vault) {
                foreach ($organizations as $organization) {
                    $metadata = json_decode($organization->metadata, true);
                    if (!is_array($metadata)) {
                        continue;
                    }

                    $hasChanges = false;

                    if (!isset($metadata['addons']) || !is_array($metadata['addons'])) {
                        $metadata['addons'] = [];
                        $hasChanges = true;
                    }

                    if (!array_key_exists('embedded_signup_enabled', $metadata['addons'])) {
                        $metadata['addons']['embedded_signup_enabled'] = true;
                        $hasChanges = true;
                    }

                    if (isset($metadata['whatsapp']) && is_array($metadata['whatsapp'])) {
                        $plainToken = $metadata['whatsapp']['access_token'] ?? null;
                        $encryptedToken = $metadata['whatsapp']['access_token_encrypted'] ?? null;

                        if (empty($encryptedToken) && !empty($plainToken)) {
                            $metadata['whatsapp']['access_token_encrypted'] = $vault->encryptToken($plainToken);
                            $hasChanges = true;
                        }

                        if (!isset($metadata['whatsapp']['token_source'])) {
                            $metadata['whatsapp']['token_source'] = !empty($metadata['whatsapp']['is_embedded_signup'])
                                ? 'embedded_signup'
                                : 'manual';
                            $hasChanges = true;
                        }

                        if (!isset($metadata['whatsapp']['access_token_expires_at'])) {
                            $metadata['whatsapp']['access_token_expires_at'] = null;
                            $hasChanges = true;
                        }

                        if (!isset($metadata['whatsapp']['access_token_last_refreshed_at'])) {
                            $metadata['whatsapp']['access_token_last_refreshed_at'] = now()->toDateTimeString();
                            $hasChanges = true;
                        }
                    }

                    if ($hasChanges) {
                        Organization::where('id', $organization->id)->update([
                            'metadata' => json_encode($metadata),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Organization::query()
            ->select(['id', 'metadata'])
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(100, function ($organizations) {
                foreach ($organizations as $organization) {
                    $metadata = json_decode($organization->metadata, true);
                    if (!is_array($metadata)) {
                        continue;
                    }

                    $hasChanges = false;

                    if (isset($metadata['whatsapp']) && is_array($metadata['whatsapp'])) {
                        unset($metadata['whatsapp']['access_token_encrypted']);
                        unset($metadata['whatsapp']['access_token_expires_at']);
                        unset($metadata['whatsapp']['access_token_last_refreshed_at']);
                        unset($metadata['whatsapp']['token_source']);
                        $hasChanges = true;
                    }

                    if (isset($metadata['addons']) && is_array($metadata['addons']) && array_key_exists('embedded_signup_enabled', $metadata['addons'])) {
                        unset($metadata['addons']['embedded_signup_enabled']);
                        $hasChanges = true;
                    }

                    if ($hasChanges) {
                        Organization::where('id', $organization->id)->update([
                            'metadata' => json_encode($metadata),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }
};

