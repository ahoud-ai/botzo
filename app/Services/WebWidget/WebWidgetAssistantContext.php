<?php

namespace App\Services\WebWidget;

use App\Models\Contact;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Services\DemoRequests\DemoRequestService;
use App\Services\Salla\SallaShoppingAssistant;
use App\Models\WebWidget;
use Illuminate\Support\Facades\Cache;

/**
 * What the assistant needs to know when it answers a website visitor: the page the visitor is
 * on, the pages it may open for them, and (on Botzo's own site) the pricing card. The assistant
 * asks for an action with a marker at the end of its reply; extractActions() takes the markers
 * out before the text is stored or sent anywhere.
 */
class WebWidgetAssistantContext
{
    private const PAGE_TTL_MINUTES = 60;

    private const MARKER = '/\[\[\s*(GO|CARD)\s*:\s*([A-Za-z0-9_-]{1,20})\s*\]\]/u';

    private const PRODUCT_MARKER = '/\[\[\s*PRODUCT\s*:\s*([0-9]{1,20})\s*\]\]/iu';

    public function rememberPage(Contact $contact, ?string $url, ?string $title): void
    {
        Cache::put($this->pageKey($contact), [
            'url' => mb_substr(trim((string) $url), 0, 500),
            'title' => mb_substr(trim((string) $title), 0, 120),
        ], now()->addMinutes(self::PAGE_TTL_MINUTES));
    }

    /**
     * Extra instructions for the assistant, or null when the contact is not a website visitor.
     */
    public function instructions(Contact $contact): ?string
    {
        if (! $contact->isWebVisitor() || ! ($widget = $this->widgetFor($contact))) {
            return null;
        }

        $settings = $widget->settingsWithDefaults();
        $page = (array) Cache::get($this->pageKey($contact), []);
        $lines = ['The customer is chatting from the website chat widget, not WhatsApp. Keep replies short and spoken-style (1 to 3 sentences, one question at a time).'];

        if (! empty($page['url'])) {
            $lines[] = sprintf('The visitor is now on the page "%s" (%s).', $page['title'] ?: $page['url'], $page['url']);
        }

        $pages = WebWidgetPages::forWidget($settings);
        if ($pages !== []) {
            $lines[] = 'You can take the visitor to one of these pages of the website, and only these:';
            foreach ($pages as $item) {
                $lines[] = sprintf('- %s: %s (%s)', $item['key'], $item['label'], $item['url']);
            }
            $lines[] = 'When the visitor wants to see one of them, say one short line first (for example: أبشر، خلني أوديك صفحة الأسعار 👇) and end your reply with the marker [[GO:key]], for example [[GO:p1]]. Never write links for these pages yourself and never use a key that is not listed.';
        }

        if ($this->offersPricingCard($widget)) {
            $lines[] = 'When the visitor asks about prices or plans, answer briefly and end your reply with the marker [[CARD:pricing]] to show the pricing card under your message.';
            $lines[] = 'When the visitor wants a demo, a call, a consultation, or to talk to sales about subscribing, say one short line (for example: أبشر! عبّ بياناتك وفريقنا يتواصل معك على واتساب 👇) and end your reply with the marker [[CARD:demo]] to show the booking form. Do not ask for their name or number in the chat; the form collects them. Use this marker instead of opening a demo page.';
        }

        array_push($lines, ...app(SallaShoppingAssistant::class)->instructions($contact, $widget));

        return implode("\n", $lines);
    }

    /**
     * Remove the action markers from a reply.
     *
     * @return array{text: string, action: ?array{type:string, key?:string, keys?:list<string>}}
     */
    public static function extractActions(string $text): array
    {
        $action = null;

        if (preg_match(self::MARKER, $text, $match) === 1) {
            $action = ['type' => strtoupper($match[1]) === 'GO' ? 'navigate' : 'card', 'key' => $match[2]];
        }

        if (preg_match_all(self::PRODUCT_MARKER, $text, $products) > 0) {
            $action = ['type' => 'products', 'keys' => array_slice(array_values(array_unique($products[1])), 0, SallaShoppingAssistant::MAX_CARDS)];
        }

        $clean = trim((string) preg_replace([self::MARKER, self::PRODUCT_MARKER], '', $text));

        return ['text' => $clean, 'action' => $action];
    }

    /**
     * The action the widget should run for a stored reply, resolved against the widget's settings.
     */
    public function resolveAction(WebWidget $widget, mixed $stored): ?array
    {
        if (! is_array($stored)) {
            return null;
        }

        if (($stored['type'] ?? null) === 'navigate') {
            $page = WebWidgetPages::find($widget->settingsWithDefaults(), (string) ($stored['key'] ?? ''));

            return $page ? ['type' => 'navigate', 'label' => $page['label'], 'url' => $page['url']] : null;
        }

        if (($stored['type'] ?? null) === 'products') {
            return app(SallaShoppingAssistant::class)->cards($widget, (array) ($stored['keys'] ?? []));
        }

        if (($stored['type'] ?? null) === 'card' && $this->isBotzoSite($widget)) {
            return match ($stored['key'] ?? null) {
                'pricing' => $this->pricingCard(),
                'demo' => ['type' => 'demo_form'],
                'demo_done' => ['type' => 'demo_done', 'whatsapp_url' => $this->companyWhatsappUrl()],
                default => null,
            };
        }

        return null;
    }

    /**
     * Botzo's own site (botzo.net): the cards about Botzo itself (plans, demo booking) show only there.
     */
    public function isBotzoSite(WebWidget $widget): bool
    {
        return (int) $widget->organization_id === (int) app(SiteWidget::class)->organization()?->id;
    }

    /**
     * "Talk to us now" link to the company number from the site's contact settings, with a ready message.
     */
    private function companyWhatsappUrl(): ?string
    {
        $settings = Setting::whereIn('key', ['frontend_contact_phone_primary', 'phone'])->pluck('value', 'key');
        $phone = trim((string) ($settings['frontend_contact_phone_primary'] ?? '')) ?: trim((string) ($settings['phone'] ?? ''));

        return DemoRequestService::whatsappUrl($phone, 'السلام عليكم، أرسلت طلب عرض توضيحي من موقع بوتزو');
    }

    /**
     * The pricing card is about Botzo's own plans, so only the botzo.net widget shows it.
     */
    public function offersPricingCard(WebWidget $widget): bool
    {
        return $this->isBotzoSite($widget);
    }

    /**
     * The most requested plan (the middle tier, as on the pricing page) with its monthly price.
     */
    public function pricingCard(): ?array
    {
        $plans = SubscriptionPlan::where('status', 'active')->whereNull('deleted_at')->where('period', 'monthly')->get()
            ->sortBy(fn (SubscriptionPlan $plan) => $plan->tierRank())->values();

        if ($plans->isEmpty()) {
            return null;
        }

        $featured = $plans->get(intdiv($plans->count(), 2)) ?? $plans->first();

        return [
            'type' => 'pricing',
            'name' => trim((string) ($featured->name_ar ?: $featured->name)),
            'name_en' => trim((string) ($featured->name_en ?: $featured->name)),
            'price' => (float) $featured->price,
            'currency' => 'SAR',
            'period' => 'monthly',
            'cta_url' => '/signup',
            'more_url' => '/pricing',
        ];
    }

    private function widgetFor(Contact $contact): ?WebWidget
    {
        return WebWidget::where('organization_id', $contact->organization_id)->first();
    }

    private function pageKey(Contact $contact): string
    {
        return 'web-widget:page:'.$contact->id;
    }
}
