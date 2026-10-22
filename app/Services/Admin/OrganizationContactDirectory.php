<?php

namespace App\Services\Admin;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read-only list of an organization's contacts for the admin organization page.
 * A main organization includes its branch workspaces; a branch shows its own contacts only.
 */
class OrganizationContactDirectory
{
    public const PER_PAGE = 25;

    /**
     * @return array<int, string> organization id => name
     */
    public function workspaces(Organization $organization): array
    {
        $query = Organization::query()->whereNull('deleted_at');

        if ($organization->parent_organization_id) {
            $query->where('id', $organization->id);
        } else {
            $query->where(fn ($q) => $q->where('id', $organization->id)->orWhere('parent_organization_id', $organization->id));
        }

        return $query->pluck('name', 'id')->map(fn ($name) => (string) $name)->all();
    }

    public function count(Organization $organization): int
    {
        return Contact::query()->whereIn('organization_id', array_keys($this->workspaces($organization)))->count();
    }

    public function paginate(Organization $organization, ?string $search, int $page = 1): LengthAwarePaginator
    {
        $workspaces = $this->workspaces($organization);
        $search = trim((string) $search);

        $paginator = Contact::query()
            ->whereIn('contacts.organization_id', array_keys($workspaces))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn ($q) => $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->select(['contacts.id', 'contacts.uuid', 'contacts.first_name', 'contacts.last_name', 'contacts.phone', 'contacts.email', 'contacts.organization_id', 'contacts.ai_assistance_enabled', 'contacts.created_at'])
            ->addSelect(['last_message_at' => Chat::query()
                ->select('created_at')
                ->whereColumn('chats.contact_id', 'contacts.id')
                ->whereNull('chats.deleted_at')
                ->orderByDesc('created_at')
                ->limit(1)])
            ->orderByDesc('contacts.id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));

        $paginator->getCollection()->transform(fn (Contact $contact) => [
            'uuid' => (string) $contact->uuid,
            'name' => $contact->full_name,
            'phone' => $contact->formatted_phone_number ?: $contact->phone,
            'email' => $contact->email,
            'workspace' => count($workspaces) > 1 ? ($workspaces[$contact->organization_id] ?? null) : null,
            'ai_enabled' => (bool) $contact->ai_assistance_enabled,
            'created_at' => $contact->created_at,
            'last_message_at' => $contact->getRawOriginal('last_message_at'),
        ]);

        return $paginator;
    }
}
