<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\WebWidget;
use App\Models\WebWidgetSession;
use App\Services\WebWidget\WebWidgetEntitlements;
use App\Services\WebWidget\WebWidgetPages;
use App\Services\WebWidget\WebWidgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $settings = $widget->settingsWithDefaults();
        $organizationName = (string) Organization::where('id', $widget->organization_id)->value('name');
        $entitlements = app(WebWidgetEntitlements::class)->for((int) $widget->organization_id);

        return response()->json(array_filter([
            'token' => $opened['token'],
            'settings' => [
                'theme' => in_array($settings['theme'], (array) config('web_widget.themes', []), true) ? $settings['theme'] : 'mochi',
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
            ],
            'messages' => $this->widgets->messages($opened['session']),
        ], fn ($value) => $value !== null));
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
        ]);

        // Control characters have no place in a chat message.
        $text = trim((string) preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $data['text']));
        if ($text === '') {
            return response()->json(['error' => 'empty_message'], 422);
        }

        $received = $this->widgets->receive($session, $text);
        if (isset($received['limited'])) {
            return response()->json(['error' => $received['limited']], 429);
        }

        return response()->json([
            'messages' => $this->widgets->messages($session->refresh(), (int) $received['chat']->id - 1),
            'awaiting_team' => ! $received['replied'],
        ]);
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

        return $widget && $widget->allowsOrigin($this->origin($request)) ? $widget : null;
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
