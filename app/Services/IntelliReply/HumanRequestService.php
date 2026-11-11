<?php

namespace App\Services\IntelliReply;

use App\Events\HumanRequestsChanged;
use App\Models\ChatTicket;
use App\Models\Contact;
use App\Models\ConversationHumanRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\OrganizationHierarchyService;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

/**
 * Conversations waiting for the team, shown in the bell until someone deals with them.
 * A contact has one open request at most, so a customer repeating themselves alerts nobody twice.
 */
class HumanRequestService
{
    private const LIST_LIMIT = 50;

    private const HISTORY_DAYS = 30;

    /**
     * @return array{request: ConversationHumanRequest, created: bool}
     */
    public function open(Contact $contact, string $reason): array
    {
        $existing = $this->openFor($contact);
        if ($existing) {
            return ['request' => $this->raiseToBlocking($existing, $reason), 'created' => false];
        }

        try {
            $request = ConversationHumanRequest::create([
                'organization_id' => (int) $contact->organization_id,
                'contact_id' => (int) $contact->id,
                'open_contact_id' => (int) $contact->id,
                'reason' => $reason,
                'blocks_assistant' => in_array($reason, ConversationHumanRequest::BLOCKING_REASONS, true),
                'opened_at' => now(),
            ]);
        } catch (QueryException $exception) {
            // Another message of the same customer opened it a moment ago.
            $existing = $this->openFor($contact);
            if (! $existing) {
                throw $exception;
            }

            return ['request' => $this->raiseToBlocking($existing, $reason), 'created' => false];
        }

        $this->announce($contact, true);

        return ['request' => $request, 'created' => true];
    }

    public function close(Contact $contact, string $resolution, ?int $userId = null): void
    {
        $request = $this->openFor($contact);

        if ($request) {
            $this->resolve($request, $contact, $resolution, $userId);
        }
    }

    public function hasOpen(Contact $contact): bool
    {
        return $this->openFor($contact) !== null;
    }

    public function blocksAssistant(Contact $contact): bool
    {
        return ConversationHumanRequest::query()
            ->where('open_contact_id', $contact->id)
            ->where('blocks_assistant', true)
            ->exists();
    }

    /** @param array<int, int> $contactIds */
    public function forgetContacts(array $contactIds): void
    {
        if ($contactIds !== []) {
            ConversationHumanRequest::query()->whereIn('contact_id', $contactIds)->delete();
        }
    }

    public function forgetOrganization(int $organizationId): void
    {
        ConversationHumanRequest::query()->where('organization_id', $organizationId)->delete();
    }

    /**
     * What the signed-in user may see for this organization.
     *
     * @return array{count: int, items: array<int, array<string, mixed>>}
     */
    public function visibleTo(int $userId, int $organizationId): array
    {
        $query = $this->visibleQuery($userId, $organizationId);

        return [
            'count' => (clone $query)->count(),
            'items' => $query->with('contact:id,uuid,first_name,last_name,phone,channel')
                ->orderByDesc('opened_at')
                ->orderByDesc('id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (ConversationHumanRequest $request) => $this->present($request))
                ->all(),
        ];
    }

    /**
     * Requests closed in the last days, with how and by whom.
     *
     * @return array<int, array<string, mixed>>
     */
    public function closedVisibleTo(int $userId, int $organizationId): array
    {
        $requests = $this->visibleQuery($userId, $organizationId, open: false)
            ->where('resolved_at', '>=', now()->subDays(self::HISTORY_DAYS))
            ->with('contact:id,uuid,first_name,last_name,phone,channel')
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get();

        $names = User::query()
            ->whereIn('id', $requests->pluck('resolved_by')->filter()->unique())
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn (User $user) => [$user->id => trim($user->first_name.' '.$user->last_name)]);

        return $requests->map(fn (ConversationHumanRequest $request) => $this->present($request) + [
            'resolved_at' => $request->resolved_at?->toIso8601String(),
            'resolution' => $request->resolution,
            'resolved_by_name' => $request->resolved_by ? ($names[$request->resolved_by] ?? null) : null,
        ])->all();
    }

    /** Where the person dealing with this request has to go. */
    private function present(ConversationHumanRequest $request): array
    {
        return [
            'uuid' => $request->uuid,
            'name' => trim((string) $request->contact?->first_name.' '.(string) $request->contact?->last_name)
                ?: (string) $request->contact?->phone,
            'channel' => $request->contact?->channel,
            'reason' => $request->reason,
            'opened_at' => $request->opened_at?->toIso8601String(),
            'action_url' => $request->contact ? '/chats/'.$request->contact->uuid : null,
        ];
    }

    /** @return bool false when this user has no such open request */
    public function closeVisible(string $uuid, int $userId, int $organizationId): bool
    {
        $request = $this->visibleQuery($userId, $organizationId)->where('uuid', $uuid)->with('contact')->first();

        if (! $request) {
            return false;
        }

        $this->resolve($request, $request->contact, ConversationHumanRequest::RESOLUTION_MANUAL_CLOSE, $userId);

        return true;
    }

    private function visibleQuery(int $userId, int $organizationId, bool $open = true): Builder
    {
        $query = ConversationHumanRequest::query()
            ->where('organization_id', $organizationId)
            ->when($open, fn (Builder $requests) => $requests->whereNotNull('open_contact_id'))
            ->when(! $open, fn (Builder $requests) => $requests->whereNull('open_contact_id')->whereNotNull('resolved_at'))
            ->whereHas('contact', fn (Builder $contacts) => $contacts->where('organization_id', $organizationId)->whereNull('deleted_at'));

        $permissions = new PermissionService();

        // The same rule as ChatAccessService::canViewContact.
        if ($permissions->isOwner($organizationId)) {
            return $query;
        }

        if ($permissions->can('chats.view_assigned_only', $organizationId)) {
            return $query->whereIn('contact_id', ChatTicket::query()->where('assigned_to', $userId)->select('contact_id'));
        }

        return $permissions->can('chats.view_all', $organizationId) ? $query : $query->whereRaw('1 = 0');
    }

    private function openFor(Contact $contact): ?ConversationHumanRequest
    {
        return ConversationHumanRequest::query()->where('open_contact_id', $contact->id)->first();
    }

    /** A customer who now asks for a person outranks a softer reason; nobody is alerted a second time. */
    private function raiseToBlocking(ConversationHumanRequest $request, string $reason): ConversationHumanRequest
    {
        if (! $request->blocks_assistant && in_array($reason, ConversationHumanRequest::BLOCKING_REASONS, true)) {
            $request->update(['reason' => $reason, 'blocks_assistant' => true]);
        }

        return $request;
    }

    private function resolve(ConversationHumanRequest $request, ?Contact $contact, string $resolution, ?int $userId): void
    {
        $request->update([
            'open_contact_id' => null,
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution' => $resolution,
        ]);

        if ($contact) {
            $this->announce($contact, false);
        }
    }

    /** The bell must never stop a reply to a customer, so a broadcast failure only gets reported. */
    private function announce(Contact $contact, bool $opened): void
    {
        try {
            event(new HumanRequestsChanged($this->recipientIds($contact), $opened));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** @return list<int> */
    private function recipientIds(Contact $contact): array
    {
        $organizationId = (int) $contact->organization_id;
        $assignedTo = ChatTicket::query()->where('contact_id', $contact->id)->value('assigned_to');

        $recipients = $this->membersSeeingEverything($organizationId);
        if ($assignedTo) {
            $recipients[] = (int) $assignedTo;
        }

        $hierarchy = app(OrganizationHierarchyService::class);
        $ownerOrganizationId = $hierarchy->isBranch($organizationId) ? $hierarchy->billingOwnerId($organizationId) : null;
        if ($ownerOrganizationId && $ownerOrganizationId !== $organizationId) {
            $recipients = array_merge($recipients, $this->membersSeeingEverything((int) $ownerOrganizationId, true));
        }

        return array_values(array_unique($recipients));
    }

    /** @return list<int> */
    private function membersSeeingEverything(int $organizationId, bool $ownersOnly = false): array
    {
        return Team::query()
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->with('organizationRole')
            ->get()
            ->filter(function (Team $team) use ($ownersOnly) {
                $role = $team->organizationRole;
                if (! $role) {
                    return false;
                }
                if ($role->isOwnerRole()) {
                    return true;
                }

                return ! $ownersOnly && $role->hasPermission('chats.view_all') && ! $role->hasPermission('chats.view_assigned_only');
            })
            ->map(fn (Team $team) => (int) $team->user_id)
            ->values()
            ->all();
    }
}
