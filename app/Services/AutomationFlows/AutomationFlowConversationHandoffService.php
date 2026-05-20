<?php

namespace App\Services\AutomationFlows;

use App\Models\ChatLog;
use App\Models\ChatTicket;
use App\Models\ChatTicketLog;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Team;
use App\Services\AddonStateService;
use App\Services\ChatTicketProvisioningService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AutomationFlowConversationHandoffService
{
    public function __construct(
        private readonly AddonStateService $addonState
    ) {
    }

    public function capabilitiesForOrganization(int $organizationId): array
    {
        $metadata = $this->organizationMetadata($organizationId);
        $agents = $this->assignableAgentsForOrganization($organizationId);

        return [
            'ticketing_active' => (bool) Arr::get($metadata, 'tickets.active', false),
            'auto_assignment_enabled' => (bool) Arr::get($metadata, 'tickets.auto_assignment', false),
            'reassign_reopened_chats' => (bool) Arr::get($metadata, 'tickets.reassign_reopened_chats', false),
            'ai_assistant_module_enabled' => $this->addonState->isModuleEnabledForOrganization('AI Assistant', $organizationId),
            'ai_assistant_active' => (bool) Arr::get($metadata, 'ai.active', false),
            'assignable_agents' => $agents,
            'has_assignable_agents' => !empty($agents),
        ];
    }

    public function assignableAgentsForOrganization(int $organizationId): array
    {
        return Team::query()
            ->with('user:id,first_name,last_name,email')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->get(['id', 'organization_id', 'user_id'])
            ->filter(fn (Team $team) => $team->user_id !== null && $team->user !== null)
            ->map(function (Team $team): array {
                $name = trim((string) ($team->user->first_name ?? '') . ' ' . (string) ($team->user->last_name ?? ''));

                return [
                    'user_id' => (int) $team->user_id,
                    'team_id' => (int) $team->id,
                    'name' => $name !== '' ? $name : (string) ($team->user->email ?? __('Assigned agent')),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function assignToAgent(
        Contact $contact,
        string $assignmentMode = 'auto_assign',
        ?int $agentUserId = null,
        bool $reopenClosed = true,
        string $reason = 'flow_assignment',
    ): array {
        $capabilities = $this->capabilitiesForOrganization((int) $contact->organization_id);

        if (!($capabilities['ticketing_active'] ?? false)) {
            return [
                'successful' => false,
                'reason' => 'ticketing_inactive',
            ];
        }

        $ticket = (new ChatTicketProvisioningService((int) $contact->organization_id))
            ->ensureForContact((int) $contact->id, $reopenClosed);

        if (!$ticket) {
            return [
                'successful' => false,
                'reason' => 'ticket_not_available',
            ];
        }

        $resolvedAssignment = $this->resolveAssignment(
            (int) $contact->organization_id,
            $assignmentMode,
            $agentUserId
        );

        if (!$resolvedAssignment['valid']) {
            return [
                'successful' => false,
                'reason' => $resolvedAssignment['reason'],
            ];
        }

        $assignedUserId = $resolvedAssignment['assigned_user_id'];
        $wasAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;

        DB::transaction(function () use ($ticket, $assignedUserId, $contact, $reason, $assignmentMode, $resolvedAssignment, $wasAssignedTo): void {
            $ticket->assigned_to = $assignedUserId;
            $ticket->status = 'open';
            $ticket->updated_at = now();
            $ticket->save();

            $this->recordAssignmentLog(
                $contact,
                $assignedUserId,
                $wasAssignedTo,
                $assignmentMode,
                (string) $reason,
                $resolvedAssignment['assigned_user_name'] ?? null,
            );
        });

        return [
            'successful' => true,
            'ticket_id' => (int) $ticket->id,
            'assignment_mode' => $assignmentMode,
            'assigned_user_id' => $assignedUserId,
            'assigned_user_name' => $resolvedAssignment['assigned_user_name'] ?? null,
        ];
    }

    public function startHumanHandoff(
        Contact $contact,
        string $assignmentMode = 'auto_assign',
        ?int $agentUserId = null,
        bool $reopenClosed = true,
    ): array {
        $result = $this->assignToAgent(
            $contact,
            $assignmentMode,
            $agentUserId,
            $reopenClosed,
            'human_handoff'
        );

        if (!($result['successful'] ?? false)) {
            return $result;
        }

        $this->setAiAssistanceEnabled($contact, false);

        $this->recordTicketLog(
            (int) $contact->id,
            isset($result['assigned_user_name']) && $result['assigned_user_name']
                ? __('Conversation was handed off to customer service and assigned to :name', ['name' => $result['assigned_user_name']])
                : __('Conversation was handed off to customer service'),
        );

        return $result + [
            'handoff_target' => 'human',
        ];
    }

    public function startAiHandoff(Contact $contact): array
    {
        $capabilities = $this->capabilitiesForOrganization((int) $contact->organization_id);

        if (!($capabilities['ai_assistant_module_enabled'] ?? false) || !($capabilities['ai_assistant_active'] ?? false)) {
            return [
                'successful' => false,
                'reason' => 'ai_assistant_unavailable',
            ];
        }

        $this->setAiAssistanceEnabled($contact, true);

        return [
            'successful' => true,
            'handoff_target' => 'ai_assistant',
        ];
    }

    private function resolveAssignment(int $organizationId, string $assignmentMode, ?int $agentUserId): array
    {
        if ($assignmentMode === 'specific_agent') {
            if (!$agentUserId || !$this->organizationHasAgent($organizationId, $agentUserId)) {
                return [
                    'valid' => false,
                    'reason' => 'invalid_agent',
                ];
            }

            return [
                'valid' => true,
                'assigned_user_id' => $agentUserId,
                'assigned_user_name' => $this->agentNameForOrganization($organizationId, $agentUserId),
            ];
        }

        if ($assignmentMode === 'unassigned') {
            return [
                'valid' => true,
                'assigned_user_id' => null,
                'assigned_user_name' => null,
            ];
        }

        $assignedUserId = $this->resolveLeastBusyAgent($organizationId);

        if ($assignedUserId === null) {
            return [
                'valid' => false,
                'reason' => 'no_assignable_agents',
            ];
        }

        return [
            'valid' => true,
            'assigned_user_id' => $assignedUserId,
            'assigned_user_name' => $this->agentNameForOrganization($organizationId, $assignedUserId),
        ];
    }

    private function resolveLeastBusyAgent(int $organizationId): ?int
    {
        $teams = Team::query()
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->get(['user_id']);

        if ($teams->isEmpty()) {
            return null;
        }

        $ticketCounts = ChatTicket::query()
            ->join('contacts', 'contacts.id', '=', 'chat_tickets.contact_id')
            ->where('contacts.organization_id', $organizationId)
            ->whereIn('chat_tickets.assigned_to', $teams->pluck('user_id'))
            ->selectRaw('chat_tickets.assigned_to, COUNT(chat_tickets.id) as aggregate')
            ->groupBy('chat_tickets.assigned_to')
            ->pluck('aggregate', 'chat_tickets.assigned_to');

        $team = $teams
            ->sortBy(fn (Team $team) => (int) ($ticketCounts[$team->user_id] ?? 0))
            ->first();

        return $team?->user_id ? (int) $team->user_id : null;
    }

    private function organizationHasAgent(int $organizationId, int $userId): bool
    {
        return Team::query()
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->exists();
    }

    private function agentNameForOrganization(int $organizationId, int $userId): ?string
    {
        return collect($this->assignableAgentsForOrganization($organizationId))
            ->firstWhere('user_id', $userId)['name'] ?? null;
    }

    private function organizationMetadata(int $organizationId): array
    {
        $metadata = Organization::query()->whereKey($organizationId)->value('metadata');
        $decoded = $metadata ? json_decode($metadata, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    private function setAiAssistanceEnabled(Contact $contact, bool $enabled): void
    {
        Contact::query()
            ->whereKey($contact->id)
            ->update([
                'ai_assistance_enabled' => $enabled,
            ]);

        $contact->forceFill([
            'ai_assistance_enabled' => $enabled,
        ]);
    }

    private function recordAssignmentLog(
        Contact $contact,
        ?int $assignedUserId,
        ?int $previousAssignedUserId,
        string $assignmentMode,
        string $reason,
        ?string $assignedUserName,
    ): void {
        $description = match ($reason) {
            'human_handoff' => $assignedUserId
                ? __('Conversation was assigned to :name during customer-service handoff', ['name' => $assignedUserName ?: __('Assigned agent')])
                : __('Conversation was handed off to customer service without assigning a specific agent'),
            default => $assignedUserId
                ? __('Conversation was assigned to :name', ['name' => $assignedUserName ?: __('Assigned agent')])
                : __('Conversation assignment was cleared'),
        };

        if ($previousAssignedUserId === $assignedUserId && $assignmentMode === 'specific_agent') {
            $description = __('Conversation remains assigned to :name', ['name' => $assignedUserName ?: __('Assigned agent')]);
        }

        $this->recordTicketLog((int) $contact->id, $description);
    }

    private function recordTicketLog(int $contactId, string $description): void
    {
        $ticketLogId = ChatTicketLog::query()->insertGetId([
            'contact_id' => $contactId,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ChatLog::query()->insert([
            'contact_id' => $contactId,
            'entity_type' => 'ticket',
            'entity_id' => $ticketLogId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

}
