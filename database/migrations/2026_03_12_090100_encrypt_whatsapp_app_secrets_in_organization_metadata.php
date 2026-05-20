<?php

use App\Models\Organization;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
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
                    if (!is_array($metadata) || !is_array($metadata['whatsapp'] ?? null)) {
                        continue;
                    }

                    $plainSecret = $metadata['whatsapp']['app_secret'] ?? null;
                    $encryptedSecret = $metadata['whatsapp']['app_secret_encrypted'] ?? null;

                    if (empty($plainSecret) && empty($encryptedSecret)) {
                        continue;
                    }

                    if (!empty($plainSecret) && empty($encryptedSecret)) {
                        $metadata['whatsapp']['app_secret_encrypted'] = $vault->encryptAppSecret($plainSecret);
                    }

                    unset($metadata['whatsapp']['app_secret']);

                    Organization::where('id', $organization->id)->update([
                        'metadata' => json_encode($metadata),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Organization::query()
            ->select(['id', 'metadata'])
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(100, function ($organizations) {
                foreach ($organizations as $organization) {
                    $metadata = json_decode($organization->metadata, true);
                    if (!is_array($metadata) || !is_array($metadata['whatsapp'] ?? null)) {
                        continue;
                    }

                    if (!array_key_exists('app_secret_encrypted', $metadata['whatsapp'])) {
                        continue;
                    }

                    unset($metadata['whatsapp']['app_secret_encrypted']);

                    Organization::where('id', $organization->id)->update([
                        'metadata' => json_encode($metadata),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};
