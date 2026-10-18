<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Jobs\ProcessWebhookMediaJob;
use App\Models\Chat;
use App\Models\ChatMedia;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * Media received from customers is stored outside the public folder and opened only by
 * signed-in team members of the organization.
 */
class ChatMediaProtectionTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            Storage::disk('local')->delete($file);
        }

        parent::tearDown();
    }

    public function test_a_team_member_of_the_organization_can_open_the_file(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        $media = $this->privateMedia($organization, 'voice-bytes', 'audio/ogg');

        $response = $this->asMember($user, $organization)->get('/chat-media/'.$media->id);

        $response->assertOk();
        $this->assertSame('voice-bytes', $response->streamedContent());
        $this->assertStringStartsWith('audio/ogg', (string) $response->headers->get('Content-Type'));
        $this->assertSame(ChatMedia::privateUrl((int) $media->id), $media->path);
    }

    public function test_someone_from_another_organization_cannot_open_it(): void
    {
        [, $organization] = $this->createOwnerContext([], true);
        [$otherUser, $otherOrganization] = $this->createOwnerContext([], true);
        $media = $this->privateMedia($organization, 'voice-bytes', 'audio/ogg');

        $this->asMember($otherUser, $otherOrganization)->get('/chat-media/'.$media->id)->assertNotFound();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        [, $organization] = $this->createOwnerContext([], true);
        $media = $this->privateMedia($organization, 'voice-bytes', 'audio/ogg');

        $this->get('/chat-media/'.$media->id)->assertRedirect();
    }

    public function test_the_public_media_route_cannot_reach_private_files(): void
    {
        [, $organization] = $this->createOwnerContext([], true);
        $media = $this->privateMedia($organization, 'voice-bytes', 'audio/ogg');

        $this->get('/media/'.$media->storage_path)->assertNotFound();
        $this->get('/media/public/../'.$media->storage_path)->assertNotFound();
    }

    public function test_media_stored_before_stays_public_and_is_not_served_by_the_private_route(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        $old = ChatMedia::create(['name' => 'N/A', 'path' => 'https://botzo.test/media/public/old.ogg', 'type' => 'audio/ogg', 'size' => 10, 'location' => 'local']);
        $this->chatFor($organization, $old);

        $this->asMember($user, $organization)->get('/chat-media/'.$old->id)->assertNotFound();
        $this->assertSame('https://botzo.test/media/public/old.ogg', $old->fresh()->path);
    }

    public function test_received_files_are_written_to_the_private_folder(): void
    {
        $method = new ReflectionMethod(ProcessWebhookMediaJob::class, 'storeReceivedMedia');
        $stored = $method->invoke(new ProcessWebhookMediaJob([], 1, 4242), 'image-bytes', 'image/jpeg', 4242);
        $this->files[] = $stored['storage_path'];

        $this->assertSame('local', $stored['location']);
        $this->assertStringStartsWith('private/received/4242/', $stored['storage_path']);
        $this->assertStringEndsWith('.jpeg', $stored['storage_path']);
        $this->assertTrue(Storage::disk('local')->exists($stored['storage_path']));
        $this->assertFalse(str_starts_with(Storage::disk('local')->path($stored['storage_path']), storage_path('app/public')));
    }

    public function test_the_storage_path_is_not_exposed_to_the_browser(): void
    {
        [, $organization] = $this->createOwnerContext([], true);
        $media = $this->privateMedia($organization, 'voice-bytes', 'audio/ogg');

        $this->assertArrayNotHasKey('storage_path', $media->toArray());
        $this->assertSame('private', $media->toArray()['visibility']);
    }

    private function asMember(User $user, Organization $organization): static
    {
        return $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id]);
    }

    private function privateMedia(Organization $organization, string $content, string $mimeType): ChatMedia
    {
        $storagePath = ChatMedia::PRIVATE_FOLDER.'/'.$organization->id.'/'.Str::random(12).'.ogg';
        Storage::disk('local')->put($storagePath, $content);
        $this->files[] = $storagePath;

        $media = ChatMedia::create([
            'name' => 'N/A',
            'storage_path' => $storagePath,
            'visibility' => ChatMedia::VISIBILITY_PRIVATE,
            'type' => $mimeType,
            'size' => strlen($content),
            'location' => 'local',
        ]);
        $media->update(['path' => ChatMedia::privateUrl((int) $media->id)]);
        $this->chatFor($organization, $media);

        return $media->fresh();
    }

    private function chatFor(Organization $organization, ChatMedia $media): Chat
    {
        $contact = Contact::create([
            'first_name' => 'Customer',
            'phone' => '+9665'.random_int(10000000, 99999999),
            'organization_id' => $organization->id,
            'created_by' => 0,
        ]);

        return Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => 'wamid.'.Str::random(12),
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => json_encode(['type' => 'audio', 'audio' => ['id' => 'm1']]),
            'media_id' => $media->id,
            'status' => 'delivered',
            'created_at' => now(),
        ]);
    }
}
