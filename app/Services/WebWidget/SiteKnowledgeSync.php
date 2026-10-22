<?php

namespace App\Services\WebWidget;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Services\IntelliReply\AiEmbeddingService;
use App\Services\IntelliReply\AiKeyResolver;
use Illuminate\Support\Facades\File;
use Modules\IntelliReply\Models\Document;
use RuntimeException;

/**
 * Loads the Botzo knowledge files (database/data/botzo_knowledge/*.md) and a pricing document
 * built from the live subscription plans into an organization's AI documents, so the botzo.net
 * assistant answers from them. A document whose text did not change is left as it is.
 */
class SiteKnowledgeSync
{
    public const PRICING_TITLE = 'الباقات والأسعار';

    public function __construct(
        private readonly AiEmbeddingService $embeddings,
        private readonly AiKeyResolver $keys,
    ) {
    }

    /**
     * @return array{added: list<string>, updated: list<string>, unchanged: list<string>}
     */
    public function sync(Organization $organization): array
    {
        $metadata = json_decode((string) $organization->metadata, true) ?: [];
        $apiKey = $this->keys->resolveForOrganization($metadata, data_get($metadata, 'ai.key_source', 'auto'), (int) $organization->id)['key'] ?? null;

        if (! $apiKey) {
            throw new RuntimeException('No AI key is available for this organization.');
        }

        $report = ['added' => [], 'updated' => [], 'unchanged' => []];

        foreach ($this->documents() as $title => $content) {
            $document = Document::where('organization_id', $organization->id)->where('title', $title)->first();

            if ($document && $document->status === 'Complete' && trim((string) $document->content) === $content) {
                $report['unchanged'][] = $title;

                continue;
            }

            $vectors = [];
            foreach ($this->embeddings->chunk($content) as $chunk) {
                if (trim($chunk) !== '') {
                    [$vectors[]] = $this->embeddings->embed($metadata, $apiKey, $chunk);
                }
            }

            $report[$document ? 'updated' : 'added'][] = $title;
            $document ??= new Document(['organization_id' => $organization->id, 'title' => $title]);
            $document->forceFill([
                'source' => 'File',
                'content' => $content,
                'embeddings' => json_encode($vectors),
                'status' => 'Complete',
            ])->save();
        }

        return $report;
    }

    /**
     * Title => text of every knowledge document, the pricing one included.
     *
     * @return array<string, string>
     */
    public function documents(): array
    {
        $documents = [];

        foreach (File::glob(database_path('data/botzo_knowledge/*.md')) as $path) {
            $content = trim(str_replace(["\r\n", "\r"], "\n", (string) File::get($path)));
            $title = preg_match('/^#\s+(.+)$/m', $content, $match) ? trim($match[1]) : pathinfo($path, PATHINFO_FILENAME);
            $documents[$title] = $content;
        }

        $documents[self::PRICING_TITLE] = $this->pricingDocument();

        return $documents;
    }

    /**
     * The plans as the pricing page shows them, written out for the assistant.
     */
    public function pricingDocument(): string
    {
        $plans = SubscriptionPlan::where('status', 'active')
            ->whereNull('deleted_at')
            ->get()
            ->sortBy(fn (SubscriptionPlan $plan) => [$plan->tierRank(), $plan->period === 'yearly' ? 1 : 0])
            ->values();

        $lines = [
            '# '.self::PRICING_TITLE,
            '',
            'الأسعار بالريال السعودي، وهي نفس الأسعار المعروضة في https://botzo.net/pricing . الاشتراك شهري أو سنوي، والسنوي أوفر.',
            'رسوم رسائل واتساب من Meta غير مشمولة في الباقات وتُدفع من حسابك في Meta، وتوثيق الحساب في Meta خدمة منفصلة.',
            '',
        ];

        foreach ($plans->groupBy(fn (SubscriptionPlan $plan) => $this->planName($plan)) as $name => $group) {
            $meta = $group->first()->metadataArray();
            $lines[] = '## باقة '.$name;

            foreach ($group as $plan) {
                $lines[] = sprintf('- السعر: %s ريال %s', $this->number((float) $plan->price), $plan->period === 'yearly' ? 'في السنة' : 'في الشهر');
            }

            $lines = array_merge($lines, [
                '- المحادثات (الرسائل) شهرياً: '.$this->limit($meta['message_limit'] ?? null),
                '- جهات الاتصال: '.$this->limit($meta['contacts_limit'] ?? null),
                '- الحملات: '.$this->limit($meta['campaign_limit'] ?? null),
                '- المستخدمين (الموظفين): '.$this->limit($meta['team_limit'] ?? null),
                '- الفروع: '.$this->limit($meta['branches_limit'] ?? null),
                '- الردود الجاهزة: '.$this->limit($meta['canned_replies_limit'] ?? null),
                '- ربط واتساب بالتسجيل المضمن: '.$this->yesNo(data_get($meta, 'addons.Embedded Signup')),
                '- المساعد الذكي: '.$this->assistantLine($meta),
                '- منشئ التدفقات: '.$this->flowLine($meta),
                '- إمكانية استخدام مفتاح OpenAI خاص: '.$this->yesNo($meta['ai_organization_key_enabled'] ?? 0),
                '- استقبال رسائل العملاء بعد انتهاء الاشتراك: '.$this->yesNo($meta['receive_messages_after_expiration'] ?? 0),
            ]);

            foreach ((array) ($meta['custom_features'] ?? []) as $feature) {
                $text = trim((string) (data_get($feature, 'text_ar') ?: data_get($feature, 'text_en')));
                if ($text !== '') {
                    $lines[] = '- '.$text;
                }
            }

            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    private function planName(SubscriptionPlan $plan): string
    {
        return trim((string) ($plan->name_ar ?: $plan->name ?: $plan->name_en));
    }

    private function assistantLine(array $meta): string
    {
        if (! data_get($meta, 'addons.AI Assistant')) {
            return 'غير متوفر';
        }

        return sprintf('متوفر، حتى %s رد نصي و%s رد صوتي شهرياً', $this->limit($meta['ai_text_response_limit'] ?? null), $this->limit($meta['ai_audio_response_limit'] ?? null));
    }

    private function flowLine(array $meta): string
    {
        if (! data_get($meta, 'addons.Flow builder')) {
            return 'غير متوفر';
        }

        return sprintf(
            'متوفر، %s تدفق نشط، حتى %s خطوة في التدفق، %s تشغيل شهرياً%s',
            $this->limit($meta['flow_builder_active_flows_limit'] ?? null),
            $this->limit($meta['flow_builder_nodes_per_flow_limit'] ?? null),
            $this->limit($meta['flow_builder_monthly_runs_limit'] ?? null),
            ! empty($meta['flow_builder_advanced_enabled']) ? '، مع المنشئ المتقدم' : ''
        );
    }

    private function limit(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'غير محدد';
        }

        return (string) $value === '-1' ? 'غير محدود' : $this->number((float) $value);
    }

    private function yesNo(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || (string) $value === '1' ? 'نعم' : 'لا';
    }

    private function number(float $value): string
    {
        return number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2, '.', ',');
    }
}
