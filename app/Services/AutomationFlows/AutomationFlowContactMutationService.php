<?php

namespace App\Services\AutomationFlows;

use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactGroup;
use Illuminate\Support\Arr;

class AutomationFlowContactMutationService
{
    public function addToGroup(Contact $contact, string $groupUuid): void
    {
        $group = ContactGroup::where('organization_id', $contact->organization_id)
            ->where('uuid', $groupUuid)
            ->whereNull('deleted_at')
            ->first();

        if ($group) {
            $contact->contactGroups()->syncWithoutDetaching([$group->id]);
        }
    }

    public function removeFromGroup(Contact $contact, string $groupUuid): void
    {
        $group = ContactGroup::where('organization_id', $contact->organization_id)
            ->where('uuid', $groupUuid)
            ->whereNull('deleted_at')
            ->first();

        if ($group) {
            $contact->contactGroups()->detach($group->id);
        }
    }

    public function updateField(Contact $contact, string $fieldUuid, mixed $value): void
    {
        $field = ContactField::where('organization_id', $contact->organization_id)
            ->where('uuid', $fieldUuid)
            ->first();

        if (!$field) {
            return;
        }

        $metadata = $contact->metadata ? json_decode($contact->metadata, true) : [];
        $metadata[$field->name] = $value;

        $contact->metadata = json_encode($metadata);
        $contact->updated_at = now();
        $contact->save();
    }

    public function fieldValue(Contact $contact, string $fieldUuid): mixed
    {
        $field = ContactField::where('organization_id', $contact->organization_id)
            ->where('uuid', $fieldUuid)
            ->first();

        if (!$field) {
            return null;
        }

        $metadata = $contact->metadata ? json_decode($contact->metadata, true) : [];

        return Arr::get($metadata, $field->name);
    }
}
