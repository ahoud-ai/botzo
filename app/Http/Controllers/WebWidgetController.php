<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\DemoRequest;
use App\Models\Organization;
use App\Models\SallaStore;
use App\Models\WebWidget;
use App\Models\WebWidgetSession;
use App\Models\WebWidgetVoiceCall;
use App\Services\DemoRequests\DemoRequestService;
use App\Services\WebWidget\WebWidgetAssistantContext;
use App\Services\Salla\SallaOrderLookup;
use App\Services\Salla\SallaStoreAccess;
use App\Services\WebWidget\WebWidgetEntitlements;
use App\Services\WebWidget\WebWidgetMessageStore;
use App\Services\WebWidget\WebWidgetPages;
use App\Services\WebWidget\WebWidgetService;
use App\Services\WebWidget\WebWidgetVoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Throwable;

/**
 * Public endpoints the website widget script calls from customers' sites. No login: a request
 * is accepted only from the widget's allowed domains, with the visitor's session token.
 */
class WebWidgetController extends Controller
{
    public function __construct(private readonly WebWidgetService $widgets)
    {
    }

    public function session(Request $request, string $key): JsonResponse
    {
        $widget = $this->widget($request, $key);
        if (! $widget) {
            return $this->unavailable();
        }

        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:128'],
            'page_url' => ['nullable', 'string', 'max:2000'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $opened = $this->widgets->openSession(
            $widget,
            $data['token'] ?? null,
            $request->ip(),
            (string) $this->origin($request),
            $data['page_url'] ?? null,
            $data['locale'] ?? null
        );

        if (isset($opened['limited'])) {
            return response()->json(['error' => 'rate_limited'], 429);
        }

        return response()->json(array_filter([
            'token' => $opened['token'],
            'settings' => $this->settings($widget),
            'messages' => $this->widgets->messages($opened['session']),
        ], fn ($value) => $value !== null));
    }

    /**
     * Look and texts only, no session: the script draws the launcher from this and opens a session
     * when the visitor opens the chat. The browser keeps it for a few minutes across page views.
     */
    public function config(Request $request, string $key): JsonResponse
    {
        $widget = $this->widget($request, $key);
        if (! $widget) {
            return $this->unavailable();
        }

        return response()->json(['settings' => $this->settings($widget)])
            ->header('Cache-Control', 'private, max-age=300')
            ->header('Vary', 'Origin');
    }

    private function settings(WebWidget $widget): array
    {
        $settings = $widget->settingsWithDefaults();
        $organizationName = (string) Organization::where('id', $widget->organization_id)->value('name');
        $entitlements = app(WebWidgetEntitlements::class)->for((int) $widget->organization_id);

        return [
            'theme' => in_array($settings['theme'], array_merge((array) config('web_widget.themes', []), (array) config('web_widget.internal_themes', [])), true) ? $settings['theme'] : 'mochi',
            'accent' => $settings['accent'],
            'accent_secondary' => $settings['accent_secondary'],
            'position' => $settings['position'],
            'assistant_name' => trim((string) $settings['assistant_name']) ?: $organizationName,
            'business_label' => trim((string) $settings['business_label']) ?: $organizationName,
            'welcome_text' => (string) $settings['welcome_text'],
            'suggestions' => array_values((array) $settings['suggestions']),
            'pages' => WebWidgetPages::forWidget($settings),
            'poll_interval' => max(5, (int) config('web_widget.poll_interval_seconds', 15)),
            'max_length' => (int) config('web_widget.limits.message_max_length', 500),
            'branding' => ! ($entitlements['remove_branding'] && ! empty($settings['hide_branding'])),
            'voice' => app(WebWidgetVoiceService::class)->availability($widget)['available'],
        ];
    }

    public function store(Request $request, string $key): JsonResponse
    {
        [$widget, $session] = $this->resolveSession($request, $key);
        if (! $widget) {
            return $this->unavailable();
        }
        if (! $session) {
            return response()->json(['error' => 'session_expired'], 401);
        }

        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.(int) config('web_widget.limits.message_max_length', 500)],
            'page_url' => ['nullable', 'string', 'max:2000'],
            'page_title' => ['nullable', 'string', 'max:300'],
        ]);

        // Control characters have no place in a chat message.
        $text = trim((string) preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $data['text']));
        if ($text === '') {
            return response()->json(['error' => 'empty_message'], 422);
        }

        $received = $this->widgets->receive($session, $text, $data['page_url'] ?? null, $data['page_title'] ?? null);
        if (isset($received['limited'])) {
            return response()->json(['error' => $received['limited']], 429);
        }

        $messages = $this->widgets->messages($session->refresh(), (int) $received['chat']->id - 1);
        // The widget adds its own "the team will reply" note, which a message saying the same makes redundant.
        $alreadyTold = collect($messages)->contains(fn (array $message) => $message['from'] !== 'visitor');

        return response()->json([
            'messages' => $messages,
            'awaiting_team' => ! $received['replied'] && ! $alreadyTold,
        ]);
    }

    /**
     * Mochi's demo booking card on botzo.net: saves the request, names the inbox contact, and adds
     * the request and a confirmation to the conversation. The team is emailed (DemoRequestService).
     */
    public function demoRequest(Request $request, string $key): JsonResponse
    {
        [$widget, $session] = $this->resolveSession($request, $key);
        if (! $widget || ! app(WebWidgetAssistantContext::class)->isBotzoSite($widget)) {
            return $this->unavailable();
        }
        if (! $session || ! $session->contact_id || ! ($contact = Contact::find($session->contact_id))) {
            return response()->json(['error' => 'session_expired'], 401);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[+0-9 ()\-]+$/'],
            'business_activity' => ['nullable', 'string', 'max:120'],
            'preferred_time' => ['nullable', 'in:'.implode(',', DemoRequest::PREFERRED_TIMES)],
            'email' => ['nullable', 'email', 'max:191'],
        ]);
        $data['full_name'] = trim((string) preg_replace('/[\x{0000}-\x{001F}\x{007F}]/u', '', $data['full_name']));
        $phone = DemoRequestService::normalizePhone($data['phone']);
        if ($data['full_name'] === '' || DemoRequestService::whatsappUrl($phone) === null) {
            return response()->json(['error' => 'invalid_details'], 422);
        }

        // One booking per conversation every few minutes stops double taps and repeat spam.
        if (! Cache::add('web-widget:demo-request:'.$session->id, 1, now()->addMinutes(10))) {
            return response()->json(['error' => 'already_requested'], 429);
        }

        $demo = app(DemoRequestService::class)->create($data, DemoRequest::SOURCE_MOCHI, (int) $contact->id);

        if (str_starts_with((string) $contact->first_name, __('Website visitor'))) {
            $contact->forceFill(['first_name' => mb_substr($demo->full_name, 0, 100), 'last_name' => null])->save();
        }

        $arabic = $session->locale !== 'en';
        // Kept left-to-right inside Arabic text, so "+966..." does not show as "966...+".
        $phoneText = "\u{2066}".$demo->phone."\u{2069}";
        $times = $arabic ? ['morning' => 'الصباح', 'evening' => 'المساء', 'any' => 'أي وقت'] : ['morning' => 'Morning', 'evening' => 'Evening', 'any' => 'Any time'];
        $summary = implode("\n", array_filter([
            $arabic ? '📅 طلب عرض توضيحي' : '📅 Demo request',
            ($arabic ? 'الاسم: ' : 'Name: ').$demo->full_name,
            ($arabic ? 'واتساب: ' : 'WhatsApp: ').$phoneText,
            $demo->business_activity ? ($arabic ? 'النشاط: ' : 'Business: ').$demo->business_activity : null,
            $demo->preferred_time ? ($arabic ? 'الوقت المناسب: ' : 'Preferred time: ').$times[$demo->preferred_time] : null,
            $demo->email ? ($arabic ? 'الإيميل: ' : 'Email: ').$demo->email : null,
        ]));
        $store = app(WebWidgetMessageStore::class);
        $first = $store->storeText($contact, $summary, 'inbound', null, ['demo_request_id' => (int) $demo->id]);
        $store->storeText($contact, $arabic
            ? sprintf('تم استلام طلبك يا %s 🎉 فريقنا بيتواصل معك على واتساب على الرقم %s قريباً.', $demo->full_name, $phoneText)
            : sprintf('Thanks %s 🎉 Our team will contact you on WhatsApp at %s soon.', $demo->full_name, $phoneText),
            'outbound', null, ['widget_action' => ['type' => 'card', 'key' => 'demo_done']]);

        return response()->json([
            'messages' => $this->widgets->messages($session->refresh(), (int) $first->id - 1),
            'awaiting_team' => true,
        ]);
    }

    /**
     * "Where is my order?" in a Salla store. Both the number and the phone must match, with few tries,
     * and the phone is not stored in the conversation.
     */
    public function orderStatus(Request $request, string $key, SallaOrderLookup $orders): JsonResponse
    {
        [$widget, $session] = $this->resolveSession($request, $key);
        $store = $widget ? SallaStore::query()->where('organization_id', $widget->organization_id)->first() : null;
        if (! $store) {
            return $this->unavailable();
        }
        if (! $session) {
            return response()->json(['error' => 'session_expired'], 401);
        }

        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        if (! RateLimiter::attempt('web-widget:order-status:'.$session->id, 5, fn () => true, 3600)
            || ! RateLimiter::attempt('web-widget:order-status-store:'.$store->id, 60, fn () => true, 60)) {
            return response()->json(['error' => 'too_many_tries'], 429);
        }

        try {
            $order = $orders->find($store, $data['order_number'], $data['phone']);
        } catch (Throwable) {
            return response()->json(['error' => 'unavailable'], 503);
        }

        if (! $order) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $contact = $this->widgets->contactFor($session);
        $arabic = $session->locale !== 'en';
        $messages = app(WebWidgetMessageStore::class);
        $first = $messages->storeText($contact, ($arabic ? 'متابعة الطلب رقم ' : 'Track order ').$order['number'], 'inbound');
        $messages->storeText($contact, implode("\n", array_filter([
            sprintf($arabic ? 'طلبك رقم %s حالته الآن: %s' : 'Order %s is now: %s', $order['number'], $order['status']),
            $order['tracking_url'] ? ($arabic ? 'رابط تتبع الشحنة: ' : 'Track the shipment: ').$order['tracking_url'] : null,
        ])), 'outbound');

        return response()->json(['messages' => $this->widgets->messages($session->refresh(), (int) $first->id - 1)]);
    }

    public function index(Request $request, string $key): JsonResponse
    {
        [$widget, $session] = $this->resolveSession($request, $key);
        if (! $widget) {
            return $this->unavailable();
        }
        if (! $session) {
            return response()->json(['error' => 'session_expired'], 401);
        }

        $after = $request->query('after');

        return response()->json([
            'messages' => $this->widgets->messages($session, is_numeric($after) ? (int) $after : null),
        ]);
    }

    public function voiceStart(Request $request, string $key, WebWidgetVoiceService $voice): JsonResponse
    {
        [$widget, $session] = $this->resolveSession($request, $key);
        if (! $widget) {
            return $this->unavailable();
        }
        if (! $session) {
            return response()->json(['error' => 'session_expired'], 401);
        }

        $data = $request->validate([
            'page_url' => ['nullable', 'string', 'max:2000'],
            'page_title' => ['nullable', 'string', 'max:300'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        app(WebWidgetAssistantContext::class)->rememberPage($this->widgets->contactFor($session), $data['page_url'] ?? $session->page_url, $data['page_title'] ?? null);
        $started = $voice->start($widget, $session, ($data['locale'] ?? 'ar') === 'en' ? 'en' : 'ar');

        if (isset($started['error'])) {
            return response()->json(['error' => $started['error']], $started['error'] === WebWidgetVoiceService::UNAVAILABLE_PROVIDER ? 502 : 409);
        }

        return response()->json([
            'call_id' => $started['call']->uuid,
            'client_secret' => $started['client_secret'],
            'expires_at' => $started['expires_at'],
            'max_seconds' => $started['call']->max_seconds,
            'calls_url' => (string) config('web_widget.voice.calls_url'),
        ]);
    }

    public function voiceEnd(Request $request, string $key, string $call, WebWidgetVoiceService $voice): JsonResponse
    {
        $found = $this->voiceCall($request, $key, $call);
        if ($found instanceof JsonResponse) {
            return $found;
        }

        $ended = $voice->end($found, (int) $request->integer('seconds'));

        return response()->json(['seconds' => $ended->seconds]);
    }

    public function voiceTranscript(Request $request, string $key, string $call, WebWidgetVoiceService $voice): JsonResponse
    {
        $found = $this->voiceCall($request, $key, $call);
        if ($found instanceof JsonResponse) {
            return $found;
        }

        $data = $request->validate([
            'role' => ['required', 'in:user,assistant'],
            'text' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json(['stored' => $voice->transcript($found, $data['role'], $data['text'])]);
    }

    public function voiceTool(Request $request, string $key, string $call, WebWidgetVoiceService $voice): JsonResponse
    {
        $found = $this->voiceCall($request, $key, $call);
        if ($found instanceof JsonResponse) {
            return $found;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'in:search_knowledge,request_human'],
            'arguments' => ['nullable', 'array'],
        ]);

        return response()->json($voice->tool($found, $data['name'], (array) ($data['arguments'] ?? [])));
    }

    private function voiceCall(Request $request, string $key, string $callUuid): WebWidgetVoiceCall|JsonResponse
    {
        [$widget, $session] = $this->resolveSession($request, $key);
        if (! $widget) {
            return $this->unavailable();
        }
        if (! $session) {
            return response()->json(['error' => 'session_expired'], 401);
        }

        $call = WebWidgetVoiceCall::where('uuid', $callUuid)->where('web_widget_session_id', $session->id)->first();

        return $call ?? response()->json(['error' => 'call_not_found'], 404);
    }

    /**
     * @return array{0: ?WebWidget, 1: ?WebWidgetSession}
     */
    private function resolveSession(Request $request, string $key): array
    {
        $widget = $this->widget($request, $key);
        $token = (string) $request->header('X-Widget-Token', '');

        if (! $widget || $token === '' || strlen($token) > 128) {
            return [$widget, null];
        }

        return [$widget, $this->widgets->findSession($widget, $token)];
    }

    private function widget(Request $request, string $key): ?WebWidget
    {
        if (preg_match('/^wgt_[A-Za-z0-9]{40}$/', $key) !== 1) {
            return null;
        }

        $widget = WebWidget::where('public_key', $key)->where('is_enabled', true)->first();

        if (! $widget || ! $widget->allowsOrigin($this->origin($request))) {
            return null;
        }

        return app(SallaStoreAccess::class)->assistantAllowed((int) $widget->organization_id) ? $widget : null;
    }

    /**
     * The page's origin. Browsers send Origin on cross-site calls; a same-site GET may only carry the Referer.
     */
    private function origin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if (is_string($origin) && $origin !== '' && $origin !== 'null') {
            return $origin;
        }

        $referer = (string) $request->headers->get('Referer', '');
        $scheme = parse_url($referer, PHP_URL_SCHEME);
        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($scheme) && is_string($host) ? $scheme.'://'.$host : null;
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['error' => 'widget_unavailable'], 403);
    }
}
