<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Services\WebWidget\SiteKnowledgeSync;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\IntelliReply\Models\Document;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class SiteKnowledgeSyncTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['logging.channels.intellireply' => ['driver' => 'null']]);
        Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => [['embedding' => [0.1, 0.2, 0.3]]]])]);
    }

    public function test_the_pricing_document_is_written_from_the_live_plans(): void
    {
        SubscriptionPlan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Growth',
            'name_ar' => 'باقة اختبار النمو',
            'price' => 599,
            'period' => 'monthly',
            'status' => 'active',
            'metadata' => json_encode([
                'tier_rank' => 2,
                'message_limit' => 10000,
                'contacts_limit' => -1,
                'ai_text_response_limit' => 1500,
                'ai_audio_response_limit' => 100,
                'widget_voice_minutes_monthly' => 120,
                'widget_remove_branding' => 1,
                'addons' => ['AI Assistant' => true, 'Flow builder' => false, 'Embedded Signup' => true],
                'custom_features' => [['text_ar' => 'جلسة إعداد وتدريب 60 دقيقة', 'text_en' => '60-minute setup']],
            ]),
        ]);

        $text = app(SiteKnowledgeSync::class)->pricingDocument();

        $this->assertStringContainsString('## باقة باقة اختبار النمو', $text);
        $this->assertStringContainsString('السعر: 599 ريال في الشهر', $text);
        $this->assertStringContainsString('المحادثات (الرسائل) شهرياً: 10,000', $text);
        $this->assertStringContainsString('جهات الاتصال: غير محدود', $text);
        $this->assertStringContainsString('المساعد الذكي: متوفر، حتى 1,500 رد نصي و100 رد صوتي شهرياً', $text);
        $this->assertStringContainsString('منشئ التدفقات: غير متوفر', $text);
        $this->assertStringContainsString('مكالمات موتشي الصوتية في ويدجت الموقع: حتى 120 دقيقة شهرياً', $text);
        $this->assertStringContainsString('إخفاء «Powered by Botzo» من الويدجت: نعم', $text);
        $this->assertStringContainsString('جلسة إعداد وتدريب 60 دقيقة', $text);
    }

    public function test_documents_are_loaded_once_and_only_changed_ones_are_embedded_again(): void
    {
        [, $organization] = $this->createOwnerContext(['ai' => [
            'active' => true,
            'key_source' => 'organization',
            'api_key_encrypted' => Crypt::encryptString('sk-test'),
        ]], true);
        $sync = app(SiteKnowledgeSync::class);
        $expected = count($sync->documents());

        $first = $sync->sync($organization);

        $this->assertCount($expected, $first['added']);
        $this->assertSame($expected, Document::where('organization_id', $organization->id)->where('status', 'Complete')->whereNotNull('embeddings')->count());
        $this->assertContains('عن بوتزو Botzo', $first['added']);
        $this->assertContains(SiteKnowledgeSync::PRICING_TITLE, $first['added']);

        Document::where('organization_id', $organization->id)->where('title', 'عن بوتزو Botzo')->update(['content' => 'old text']);
        Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => [['embedding' => [0.4, 0.5, 0.6]]]])]);

        $second = $sync->sync($organization);

        $this->assertSame(['عن بوتزو Botzo'], $second['updated']);
        $this->assertSame([], $second['added']);
        $this->assertCount($expected - 1, $second['unchanged']);
    }
}
