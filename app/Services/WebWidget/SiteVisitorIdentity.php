<?php

namespace App\Services\WebWidget;

use App\Models\AutomationFlow;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Models\WebWidget;
use App\Models\WebWidgetSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Lets Mochi on botzo.net know which signed-in customer it is talking to.
 *
 * The page carries a short-lived token the server encrypted and signed (it cannot be forged or
 * read in the browser). The widget sends it when it opens a session or sends a message; only
 * Botzo's own site widget accepts it. The assistant then gets a read-only summary of that
 * customer's account. Nothing here lets the assistant change anything.
 */
class SiteVisitorIdentity
{
    private const PREFIX = 'bzid.';

    private const TOKEN_MINUTES = 180;

    private const REMEMBER_HOURS = 12;

    public function tokenFor(User $user, int $organizationId): ?string
    {
        if ($organizationId < 1 || ! $this->isMember((int) $user->id, $organizationId)) {
            return null;
        }

        return self::PREFIX.Crypt::encryptString(json_encode([
            'u' => (int) $user->id,
            'o' => $organizationId,
            'e' => now()->addMinutes(self::TOKEN_MINUTES)->timestamp,
        ]));
    }

    /**
     * The signed-in customer behind a token, or null when the token is missing, forged, expired,
     * meant for another widget, or the person is no longer in that organization.
     *
     * @return array{user_id:int, organization_id:int}|null
     */
    public function resolve(WebWidget $widget, ?string $token): ?array
    {
        $token = trim((string) $token);
        if ($token === '' || ! str_starts_with($token, self::PREFIX) || mb_strlen($token) > 2000) {
            return null;
        }

        if ((int) $widget->organization_id !== app(SiteWidget::class)->organizationId()) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString(substr($token, strlen(self::PREFIX))), true);
        } catch (Throwable) {
            return null;
        }

        $userId = (int) Arr::get((array) $payload, 'u', 0);
        $organizationId = (int) Arr::get((array) $payload, 'o', 0);
        if ($userId < 1 || $organizationId < 1 || (int) Arr::get((array) $payload, 'e', 0) < now()->timestamp) {
            return null;
        }

        return $this->isMember($userId, $organizationId) ? ['user_id' => $userId, 'organization_id' => $organizationId] : null;
    }

    /**
     * The customer this session last belonged to, or null.
     *
     * @return array{user_id:int, organization_id:int}|null
     */
    public function forSession(WebWidgetSession $session): ?array
    {
        $identity = Cache::get($this->key($session));

        return is_array($identity) && isset($identity['user_id'], $identity['organization_id']) ? $identity : null;
    }

    public function remember(WebWidgetSession $session, ?array $identity): void
    {
        $identity
            ? Cache::put($this->key($session), $identity, now()->addHours(self::REMEMBER_HOURS))
            : Cache::forget($this->key($session));
    }

    /**
     * A session someone else used must not show its chat to the person now on this browser:
     * after signing out, or when a different customer signs in. Signing in on a guest chat keeps it.
     */
    public function belongsToSomeoneElse(WebWidgetSession $session, ?array $identity): bool
    {
        $previous = $this->forSession($session);

        return $previous !== null && ($identity === null || $previous['user_id'] !== $identity['user_id']);
    }

    /**
     * Lines for the assistant about the signed-in customer, or [] for a guest.
     *
     * @return list<string>
     */
    public function instructions(Contact $contact): array
    {
        $session = WebWidgetSession::where('contact_id', $contact->id)
            ->where('organization_id', $contact->organization_id)
            ->latest('id')
            ->first();
        $identity = $session ? $this->forSession($session) : null;
        if (! $identity) {
            return [];
        }

        $user = User::find($identity['user_id']);
        $organization = Organization::find($identity['organization_id']);
        if (! $user || ! $organization) {
            return [];
        }

        return array_merge(
            ['The visitor is a signed-in Botzo customer. Their account, read only (you cannot change anything in it; tell them where to do it):'],
            array_map(fn (string $line) => '- '.$line, $this->summary($user, $organization)),
            ['Greet them by first name once, and use these facts when they help (for example: tell them to turn on ticketing before a human handoff step if it is off). Never read this list out as a whole, and never share it with anyone else.']
        );
    }

    /**
     * @return list<string>
     */
    private function summary(User $user, Organization $organization): array
    {
        $metadata = json_decode((string) $organization->metadata, true) ?: [];
        $team = Team::with('organizationRole:id,name')
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();
        $subscription = Subscription::with('plan:id,name')
            ->where('organization_id', $organization->parent_organization_id ?: $organization->id)
            ->latest('id')
            ->first();
        $flows = AutomationFlow::where('organization_id', $organization->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $lines = [
            'Name: '.$this->clip(trim($user->first_name.' '.$user->last_name), 80),
            'Business: '.$this->clip((string) $organization->name, 80),
        ];

        if ($team?->organizationRole?->name) {
            $lines[] = 'Their role: '.$this->clip((string) $team->organizationRole->name, 40);
        }

        $lines[] = $subscription
            ? sprintf('Plan: %s, subscription %s%s', $subscription->plan?->name ?: 'unknown', $subscription->status, $subscription->valid_until ? ' until '.substr((string) $subscription->valid_until, 0, 10) : '')
            : 'Plan: no subscription yet';
        $lines[] = 'WhatsApp number connected: '.(filled(data_get($metadata, 'whatsapp.phone_number_id')) ? 'yes' : 'no');
        $lines[] = 'AI assistant turned on: '.(data_get($metadata, 'ai.active') ? 'yes' : 'no');
        $lines[] = 'Ticketing turned on: '.(data_get($metadata, 'tickets.active') ? 'yes' : 'no');
        $lines[] = 'Team members: '.Team::where('organization_id', $organization->id)->whereNull('deleted_at')->count();
        $lines[] = sprintf(
            'Flows: %d published, %d paused, %d drafts',
            (int) ($flows['published'] ?? 0),
            (int) ($flows['paused'] ?? 0),
            (int) ($flows['draft'] ?? 0)
        );

        return $lines;
    }

    private function isMember(int $userId, int $organizationId): bool
    {
        return Team::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function key(WebWidgetSession $session): string
    {
        return 'web-widget:identity:'.$session->id;
    }

    private function clip(string $value, int $limit): string
    {
        // Names are typed by customers: keep them on one line so they cannot pose as instructions.
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $value)), 0, $limit);
    }
}
