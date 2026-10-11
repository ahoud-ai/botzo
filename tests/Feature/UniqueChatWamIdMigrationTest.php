<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\ChatLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * Runs the migration's down()/up() for real, so it cannot use DatabaseTransactions
 * (MySQL commits DDL implicitly). Rows it creates are removed in tearDown.
 */
class UniqueChatWamIdMigrationTest extends TestCase
{
    use CreatesOrganizationContext;

    private const MIGRATION = 'database/migrations/2026_10_11_000001_add_unique_wam_id_index_to_chats_table.php';

    private array $organizationIds = [];

    protected function tearDown(): void
    {
        if ($this->organizationIds !== []) {
            $chatIds = DB::table('chats')->whereIn('organization_id', $this->organizationIds)->pluck('id');
            DB::table('chat_logs')->where('entity_type', 'chat')->whereIn('entity_id', $chatIds)->delete();
            DB::table('chats')->whereIn('id', $chatIds)->delete();
            $userIds = DB::table('organizations')->whereIn('id', $this->organizationIds)->pluck('created_by');
            DB::table('teams')->whereIn('organization_id', $this->organizationIds)->delete();
            DB::table('organizations')->whereIn('id', $this->organizationIds)->delete();
            DB::table('users')->whereIn('id', $userIds)->delete();
        }

        parent::tearDown();
    }

    public function test_up_cleans_duplicates_before_adding_the_unique_index_and_down_restores_them(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->down();

        [, $organization] = $this->createOwnerContext();
        $this->organizationIds[] = $organization->id;

        $kept = $this->insertChat($organization->id, 'wamid.SAME');
        $duplicate = $this->insertChat($organization->id, 'wamid.SAME');
        $duplicateLogId = ChatLog::insertGetId([
            'contact_id' => 1,
            'entity_type' => 'chat',
            'entity_id' => $duplicate,
            'created_at' => now(),
        ]);
        $unrelated = $this->insertChat($organization->id, 'wamid.OTHER');

        $migration->up();

        $this->assertSame('wamid.SAME', DB::table('chats')->where('id', $kept)->value('wam_id'));
        $this->assertNull(DB::table('chats')->where('id', $kept)->value('deleted_at'));
        $this->assertNull(DB::table('chats')->where('id', $duplicate)->value('wam_id'));
        $this->assertNotNull(DB::table('chats')->where('id', $duplicate)->value('deleted_at'));
        $this->assertNotNull(DB::table('chat_logs')->where('id', $duplicateLogId)->value('deleted_at'));
        $this->assertSame('wamid.OTHER', DB::table('chats')->where('id', $unrelated)->value('wam_id'));

        $this->expectUniqueViolation(fn () => $this->insertChat($organization->id, 'wamid.SAME'));

        $migration->down();

        $this->assertSame('wamid.SAME', DB::table('chats')->where('id', $duplicate)->value('wam_id'));
        $this->assertNull(DB::table('chats')->where('id', $duplicate)->value('deleted_at'));
        $this->assertNull(DB::table('chat_logs')->where('id', $duplicateLogId)->value('deleted_at'));
        $this->assertFalse(Schema::hasTable('chat_wam_id_dedupe_backups'));

        // Leave the schema migrated for the rest of the suite.
        DB::table('chats')->where('id', $duplicate)->delete();
        DB::table('chat_logs')->where('id', $duplicateLogId)->delete();
        $migration->up();
    }

    public function test_the_same_wam_id_is_allowed_in_different_organizations(): void
    {
        [, $first] = $this->createOwnerContext();
        [, $second] = $this->createOwnerContext();
        $this->organizationIds = [$first->id, $second->id];

        $this->insertChat($first->id, 'wamid.SHARED');
        $this->insertChat($second->id, 'wamid.SHARED');

        $this->assertSame(2, Chat::where('wam_id', 'wamid.SHARED')->count());
    }

    private function insertChat(int $organizationId, string $wamId): int
    {
        return (int) DB::table('chats')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $organizationId,
            'wam_id' => $wamId,
            'contact_id' => 1,
            'type' => 'inbound',
            'metadata' => '{}',
            'status' => 'delivered',
            'created_at' => now(),
        ]);
    }

    private function expectUniqueViolation(callable $callback): void
    {
        try {
            $callback();
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected the unique (organization_id, wam_id) index to reject a duplicate.');
    }
}
