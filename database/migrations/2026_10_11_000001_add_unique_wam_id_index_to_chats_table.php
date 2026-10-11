<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes (organization_id, wam_id) unique on chats so a re-delivered WhatsApp
 * webhook can never store the same message twice.
 *
 * Existing duplicates are cleaned first without losing data: every extra copy
 * is soft-deleted, its wam_id is cleared, and the original values are kept in
 * chat_wam_id_dedupe_backups so down() can restore them exactly.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'chats_organization_wam_id_unique';

    private const BACKUP_TABLE = 'chat_wam_id_dedupe_backups';

    public function up(): void
    {
        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            Schema::create(self::BACKUP_TABLE, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('chat_id')->index();
                $table->integer('organization_id');
                $table->string('wam_id', 128);
                $table->dateTime('original_deleted_at')->nullable();
                $table->json('soft_deleted_chat_log_ids')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        $now = now('UTC')->toDateTimeString();

        DB::table('chats')
            ->select('organization_id', 'wam_id')
            ->whereNotNull('wam_id')
            ->groupBy('organization_id', 'wam_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('organization_id')
            ->get()
            ->each(function ($group) use ($now): void {
                // Keep the oldest copy that is still visible; fall back to the oldest copy overall.
                $copies = DB::table('chats')
                    ->where('organization_id', $group->organization_id)
                    ->where('wam_id', $group->wam_id)
                    ->orderByRaw('deleted_at IS NULL DESC')
                    ->orderBy('id')
                    ->get(['id', 'deleted_at']);

                foreach ($copies->slice(1) as $duplicate) {
                    $chatLogIds = DB::table('chat_logs')
                        ->where('entity_type', 'chat')
                        ->where('entity_id', $duplicate->id)
                        ->whereNull('deleted_at')
                        ->pluck('id')
                        ->all();

                    DB::table(self::BACKUP_TABLE)->insert([
                        'chat_id' => $duplicate->id,
                        'organization_id' => $group->organization_id,
                        'wam_id' => $group->wam_id,
                        'original_deleted_at' => $duplicate->deleted_at,
                        'soft_deleted_chat_log_ids' => json_encode($chatLogIds),
                        'created_at' => $now,
                    ]);

                    DB::table('chats')
                        ->where('id', $duplicate->id)
                        ->update([
                            'wam_id' => null,
                            'deleted_at' => $duplicate->deleted_at ?? $now,
                        ]);

                    if ($chatLogIds !== []) {
                        DB::table('chat_logs')->whereIn('id', $chatLogIds)->update(['deleted_at' => $now]);
                    }
                }
            });

        Schema::table('chats', function (Blueprint $table) {
            $table->unique(['organization_id', 'wam_id'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropUnique(self::INDEX_NAME);
        });

        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            return;
        }

        DB::table(self::BACKUP_TABLE)->orderBy('id')->get()->each(function ($backup): void {
            DB::table('chats')
                ->where('id', $backup->chat_id)
                ->update([
                    'wam_id' => $backup->wam_id,
                    'deleted_at' => $backup->original_deleted_at,
                ]);

            $chatLogIds = json_decode((string) $backup->soft_deleted_chat_log_ids, true) ?: [];
            if ($chatLogIds !== []) {
                DB::table('chat_logs')->whereIn('id', $chatLogIds)->update(['deleted_at' => null]);
            }
        });

        Schema::dropIfExists(self::BACKUP_TABLE);
    }
};
