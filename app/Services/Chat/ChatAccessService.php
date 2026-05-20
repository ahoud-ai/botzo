<?php

namespace App\Services\Chat;

use App\Models\ChatTicket;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Support\Collection;

class ChatAccessService
{
    public function canOpenChatArea(int $organizationId): bool
    {
        $permissionService = new PermissionService();

        return $permissionService->isOwner($organizationId)
            || $permissionService->can('chats.view_all', $organizationId)
            || $permissionService->can('chats.view_assigned_only', $organizationId);
    }

    public function canSubscribeToOrganizationStream(User $user, int $organizationId): bool
    {
        if (! $this->isActiveTeamMember($user, $organizationId)) {
            return false;
        }

        $permissionService = new PermissionService();

        if ($permissionService->isOwner($organizationId)) {
            return true;
        }

        if ($permissionService->can('chats.view_assigned_only', $organizationId)) {
            return false;
        }

        return $permissionService->can('chats.view_all', $organizationId);
    }

    public function canViewContact(Contact $contact, int $organizationId): bool
    {
        if ((int) $contact->organization_id !== (int) $organizationId) {
            return false;
        }

        $permissionService = new PermissionService();

        if ($permissionService->isOwner($organizationId)) {
            return true;
        }

        if ($permissionService->can('chats.view_assigned_only', $organizationId)) {
            return ChatTicket::where('contact_id', $contact->id)
                ->where('assigned_to', auth()->id())
                ->exists();
        }

        return $permissionService->can('chats.view_all', $organizationId);
    }

    public function visibleContacts(Collection $contacts, int $organizationId): Collection
    {
        return $contacts
            ->filter(fn (Contact $contact) => $this->canViewContact($contact, $organizationId))
            ->values();
    }

    private function isActiveTeamMember(User $user, int $organizationId): bool
    {
        return Team::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->exists();
    }
}
