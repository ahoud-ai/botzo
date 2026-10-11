<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\VerifyCsrfToken;
use App\Services\AddonStateService;
use App\Services\IntelliReply\AiKnowledgeRetriever;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\IntelliReply\Models\Document;
use Modules\IntelliReply\Models\Faq;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class AiKnowledgeRetrievalTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AddonStateService::class)->shouldReceive('isModuleEnabledForOrganization')->andReturn(true);
    }

    public function test_nothing_is_retrieved_and_no_request_is_made_without_faqs_or_documents(): void
    {
        [, $organization] = $this->createOwnerContext([], true);
        Http::fake();

        $this->assertNull($this->retrieve($organization->id, 'hello'));
        Http::assertNothingSent();
    }

    public function test_a_small_faq_list_is_always_included_without_an_embedding_request(): void
    {
        [, $organization] = $this->createOwnerContext([], true);
        Faq::create(['organization_id' => $organization->id, 'question' => 'فيه توصيل؟', 'answer' => 'أيوه، لكل المحافظات.']);
        Http::fake();

        $knowledge = $this->retrieve($organization->id, 'بتوصلوا اسكندرية؟');

        $this->assertStringContainsString("Q: فيه توصيل؟\nA: أيوه، لكل المحافظات.", $knowledge);
        Http::assertNothingSent();
    }

    public function test_only_relevant_document_chunks_are_retrieved(): void
    {
        [, $organization] = $this->createOwnerContext([], true);
        $pricing = 'PRICING '.str_repeat('a', 1000);
        $returns = 'RETURNS '.str_repeat('b', 1000);
        Document::create([
            'organization_id' => $organization->id,
            'source' => 'File',
            'title' => 'Policies',
            'content' => $pricing."\n\n".$returns,
            'embeddings' => json_encode([[1, 0, 0], [0, 1, 0]]),
            'status' => 'Complete',
        ]);

        Http::fake(['api.openai.com/v1/embeddings' => Http::sequence()
            ->push(['data' => [['embedding' => [0, 1, 0]]]])
            ->push(['data' => [['embedding' => [0, 0, 1]]]])]);

        $knowledge = $this->retrieve($organization->id, 'can I return it?');
        $this->assertStringContainsString("[Policies]\n".$returns, $knowledge);
        $this->assertStringNotContainsString('PRICING', $knowledge);

        $this->assertNull($this->retrieve($organization->id, 'what is the weather?'), 'Unrelated questions get no excerpts.');
    }

    public function test_a_large_faq_list_is_ranked_by_similarity(): void
    {
        config(['intellireply.retrieval.faq_inline_chars' => 10]);
        [, $organization] = $this->createOwnerContext([], true);
        Faq::create(['organization_id' => $organization->id, 'question' => 'Delivery?', 'answer' => 'Two days.', 'embedding' => [1, 0, 0]]);
        Faq::create(['organization_id' => $organization->id, 'question' => 'Refunds?', 'answer' => 'Within 14 days.', 'embedding' => [0, 1, 0]]);

        $this->fakeQueryEmbedding([1, 0, 0]);
        $knowledge = $this->retrieve($organization->id, 'how long is shipping?');

        $this->assertStringContainsString('Delivery?', $knowledge);
        $this->assertStringNotContainsString('Refunds?', $knowledge);
    }

    public function test_faqs_can_be_added_edited_and_deleted_within_the_organization_only(): void
    {
        [$user, $organization] = $this->createOwnerContext($this->aiMetadata(), true);
        [, $otherOrganization] = $this->createOwnerContext([], true);
        $foreign = Faq::create(['organization_id' => $otherOrganization->id, 'question' => 'Other?', 'answer' => 'Other.']);
        $this->fakeQueryEmbedding([0.5, 0.5, 0]);
        $client = fn () => $this->withoutMiddleware([CheckSubscriptionStatus::class, VerifyCsrfToken::class])
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id]);

        $client()->post('/automation/ai/faqs', ['question' => '  مواعيدكم   إيه؟ ', 'answer' => 'من 10 لـ 10'])->assertSessionHasNoErrors();
        $faq = Faq::where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('مواعيدكم إيه؟', $faq->question);
        $this->assertSame([0.5, 0.5, 0], $faq->embedding);

        $client()->put('/automation/ai/faqs/'.$faq->uuid, ['question' => 'مواعيدكم إيه؟', 'answer' => 'من 9 لـ 11'])->assertSessionHasNoErrors();
        $this->assertSame('من 9 لـ 11', $faq->fresh()->answer);

        $client()->put('/automation/ai/faqs/'.$foreign->uuid, ['question' => 'x', 'answer' => 'y'])->assertNotFound();
        $client()->delete('/automation/ai/faqs/'.$foreign->uuid);
        $this->assertNotNull($foreign->fresh());

        $client()->delete('/automation/ai/faqs/'.$faq->uuid);
        $this->assertNull($faq->fresh());
    }

    public function test_the_faq_list_is_capped(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        foreach (range(1, Faq::MAX_PER_ORGANIZATION) as $i) {
            Faq::create(['organization_id' => $organization->id, 'question' => "Q{$i}", 'answer' => 'A']);
        }

        $this->withoutMiddleware([CheckSubscriptionStatus::class, VerifyCsrfToken::class])
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/ai/faqs', ['question' => 'One more?', 'answer' => 'No.']);

        $this->assertSame(Faq::MAX_PER_ORGANIZATION, Faq::where('organization_id', $organization->id)->count());
    }

    public function test_uploaded_documents_are_embedded_per_chunk(): void
    {
        [$user, $organization] = $this->createOwnerContext($this->aiMetadata(), true);
        $this->fakeQueryEmbedding([0.1, 0.2, 0.3]);
        $content = str_repeat('x', 1000)."\n\n".str_repeat('y', 1000);

        $this->withoutMiddleware([CheckSubscriptionStatus::class, VerifyCsrfToken::class])
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/automation/upload/document', [
                'title' => 'Menu',
                'file' => UploadedFile::fake()->createWithContent('menu.txt', $content),
            ])
            ->assertSessionHas('status.type', 'success');

        $document = Document::where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('Complete', $document->status);
        $this->assertCount(2, json_decode($document->embeddings, true));
        Http::assertSentCount(2);
    }

    private function retrieve(int $organizationId, string $query): ?string
    {
        return app(AiKnowledgeRetriever::class)->retrieve($organizationId, [], 'sk-test', $query);
    }

    private function fakeQueryEmbedding(array $vector): void
    {
        Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => [['embedding' => $vector]]])]);
    }

    private function aiMetadata(): array
    {
        return ['ai' => ['active' => true, 'key_source' => 'organization', 'api_key_encrypted' => Crypt::encryptString('sk-test')]];
    }
}
