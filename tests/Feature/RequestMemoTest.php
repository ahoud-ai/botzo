<?php

namespace Tests\Feature;

use App\Helpers\DateTimeHelper;
use App\Http\Resources\ContactResource;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\OrganizationHierarchyService;
use App\Support\RequestMemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestMemoTest extends TestCase
{
    use RefreshDatabase;

    private function organization(array $metadata = []): Organization
    {
        return Organization::create([
            'uuid' => (string) Str::uuid(),
            'identifier' => 'memo-'.Str::random(6),
            'name' => 'Memo Org',
            'metadata' => json_encode($metadata),
            'created_by' => 0,
        ]);
    }

    public function test_a_value_is_computed_once_per_request(): void
    {
        $calls = 0;
        RequestMemo::remember('demo', 1, function () use (&$calls) { return ++$calls; });
        $value = RequestMemo::remember('demo', 1, function () use (&$calls) { return ++$calls; });

        $this->assertSame(1, $value);
        $this->assertSame(1, $calls);
    }

    public function test_organization_lookups_hit_the_database_once_and_refresh_after_a_save(): void
    {
        $organization = $this->organization(['timezone' => 'Asia/Riyadh']);
        session()->put('current_organization', $organization->id);

        DB::enableQueryLog();
        foreach (range(1, 5) as $i) {
            DateTimeHelper::convertToOrganizationTimezone('2026-01-01 00:00:00');
            app(OrganizationHierarchyService::class)->find($organization->id);
        }
        $this->assertCount(2, DB::getQueryLog(), 'One timezone read and one organization read.');

        $organization->update(['metadata' => json_encode(['timezone' => 'Africa/Cairo'])]);

        $this->assertSame('Africa/Cairo', DateTimeHelper::convertToOrganizationTimezone('2026-01-01 00:00:00')->getTimezone()->getName());
    }

    public function test_unread_badges_for_a_page_come_from_one_query(): void
    {
        $organization = $this->organization();
        foreach (range(1, 3) as $i) {
            $contact = Contact::create(['organization_id' => $organization->id, 'first_name' => 'C'.$i, 'phone' => '+96650000000'.$i, 'created_by' => 0]);
            foreach (range(1, $i) as $j) {
                Chat::create(['organization_id' => $organization->id, 'contact_id' => $contact->id, 'type' => 'inbound', 'metadata' => '{}', 'status' => 'delivered', 'is_read' => 0]);
            }
            Chat::create(['organization_id' => $organization->id, 'contact_id' => $contact->id, 'type' => 'inbound', 'metadata' => '{}', 'status' => 'delivered', 'is_read' => 1]);
        }

        $page = Contact::withUnreadCounts(Contact::where('organization_id', $organization->id)->orderBy('id')->paginate(10));

        DB::enableQueryLog();
        $rows = ContactResource::collection($page)->resolve(request());

        $this->assertSame([1, 2, 3], array_column($rows, 'unread_messages'));
        $this->assertArrayNotHasKey('unread_messages_count', $rows[0]);
        $this->assertSame([], array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'count(*)')));
    }
}
