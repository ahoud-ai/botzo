<?php

namespace App\Console\Commands;

use App\Models\ChatMedia;
use App\Models\SallaStore;
use App\Models\SallaWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeSallaStoresCommand extends Command
{
    private const EVENT_RETENTION_DAYS = 30;

    protected $signature = 'salla:purge';

    protected $description = 'Delete conversations of Salla stores uninstalled past their retention period, and old webhook events';

    public function handle(): int
    {
        $due = SallaStore::query()
            ->where('status', SallaStore::STATUS_UNINSTALLED)
            ->where('purge_after', '<=', now())
            ->pluck('merchant_id');

        foreach ($due as $merchantId) {
            Cache::lock(SallaStore::lockKey((int) $merchantId), 300)->block(10, fn () => $this->purge((int) $merchantId));
        }

        $events = SallaWebhookEvent::query()->where('created_at', '<', now()->subDays(self::EVENT_RETENTION_DAYS))->delete();

        $this->info("Purged {$due->count()} stores and {$events} events.");

        return self::SUCCESS;
    }

    private function purge(int $merchantId): void
    {
        // Read again under the lock: the merchant may have reinstalled a moment ago.
        $store = SallaStore::query()
            ->where('merchant_id', $merchantId)
            ->where('status', SallaStore::STATUS_UNINSTALLED)
            ->where('purge_after', '<=', now())
            ->first();

        if (! $store || ! $store->organization_id) {
            return;
        }

        $organizationId = (int) $store->organization_id;

        DB::table('web_widget_voice_calls')->where('organization_id', $organizationId)->delete();
        DB::table('web_widget_sessions')->where('organization_id', $organizationId)->delete();

        DB::table('contacts')->where('organization_id', $organizationId)->select('id')->chunkById(200, function ($contacts) use ($organizationId) {
            $contactIds = $contacts->pluck('id')->all();

            $this->deleteMedia($organizationId, $contactIds);

            foreach (['chat_logs', 'chat_notes', 'chat_ticket_logs', 'chat_tickets', 'chats'] as $table) {
                DB::table($table)->whereIn('contact_id', $contactIds)->delete();
            }

            DB::table('contacts')->whereIn('id', $contactIds)->delete();
        });

        DB::table('chats')->where('organization_id', $organizationId)->delete();

        DB::table('salla_products')->where('salla_store_id', $store->id)->delete();

        $store->forceFill(['store_email' => null, 'purge_after' => null, 'products_synced_at' => null])->save();
    }

    /** Files customers sent are private to this organization, so they go with the conversation. */
    private function deleteMedia(int $organizationId, array $contactIds): void
    {
        $mediaIds = DB::table('chats')
            ->where('organization_id', $organizationId)
            ->whereIn('contact_id', $contactIds)
            ->whereNotNull('media_id')
            ->pluck('media_id');

        ChatMedia::query()->whereIn('id', $mediaIds)->get()->each(function (ChatMedia $media) {
            if ($media->isPrivate()) {
                Storage::disk($media->location === 'amazon' ? 's3' : 'local')->delete($media->storage_path);
            }

            $media->delete();
        });
    }
}
