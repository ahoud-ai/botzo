<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\ContactContactGroup;
use App\Models\ContactGroup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\PhoneService;

class ContactService
{
    private $organizationId;

    public function __construct($organizationId)
    {
        $this->organizationId = $organizationId;
    }

    public function store(object $request, $uuid = null){
        $contact = $uuid === null
            ? new Contact()
            : Contact::where('organization_id', $this->organizationId)
                ->where('uuid', $uuid)
                ->whereNull('deleted_at')
                ->firstOrFail();
        $contact->first_name = $request->first_name;
        $contact->last_name = $request->last_name;
        $contact->email = $request->email;

        $contact->phone = PhoneService::getE164Format($request->phone);

        if($request->hasFile('file')){
            $storage = app(SettingValueService::class)->getString('storage_system', 'local');
            $fileName = $request->file('file')->getClientOriginalName();
            $fileContent = $request->file('file');

            if($storage === 'local'){
                $file = Storage::disk('local')->put('public', $fileContent);
                $mediaFilePath = $file;

                $contact->avatar = '/media/' . ltrim($mediaFilePath, '/');
            } else if($storage === 'aws') {
                $file = $request->file('file');
                $uploadedFile = $file->store('uploads/media/contacts/' . $this->organizationId, 's3');
                $mediaFilePath = Storage::disk('s3')->url($uploadedFile);

                $contact->avatar = $mediaFilePath;
            }
        }

        if($uuid === null){
            $contact->organization_id = $this->organizationId;
            $contact->created_by = auth()->user() ? auth()->user()->id : 0;
            $contact->created_at = now();
        }

        $address = json_encode([
            'street' => $request->street,
            'city' => $request->city,
            'state' => $request->state,
            'zip' => $request->zip,
            'country' => $request->country,
        ]);
        
        $contact->address = $address;
        $contact->metadata = json_encode($request->metadata);
        $contact->updated_at = now();
        $contact->save();

        if($request->group){
            $groupUuids = array_map('trim', $request->group);
            $groupIds = ContactGroup::where('organization_id', $this->organizationId)
                ->whereNull('deleted_at')
                ->whereIn('uuid', $groupUuids)
                ->pluck('id')
                ->toArray();
            $contact->contactGroups()->sync($groupIds);
        }

        return $contact;
    }

    public function favorite(object $request, $uuid){
        $contact = Contact::where('organization_id', $this->organizationId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();
        $contact->is_favorite = $request->favorite;
        $contact->updated_at = date('Y-m-d H:i:s');
        $contact->save();
    }

    public function delete($uuids){
        $deletedContacts = [];

        if (empty($uuids)) {
            // Delete all contacts (soft delete)
            $contacts = Contact::where('organization_id', $this->organizationId)
                ->whereNull('deleted_at')
                ->get();
            Contact::whereNotNull('id')->where('organization_id', $this->organizationId)->delete();

            // Prepare deleted contacts for the webhook
            foreach ($contacts as $contact) {
                $deletedContacts[] = [
                    'uuid' => $contact->uuid,
                    'deleted_at' => now()->toISOString(), // Assuming you're using Laravel's Carbon
                ];
            }

            //Mark all unread chats as read
            Chat::where('organization_id', $this->organizationId)
                ->where('type', 'inbound')
                ->whereNull('deleted_at')
                ->where('is_read', 0)
                ->update([
                    'is_read' => 1
                ]);
        } else {
            $contacts = Contact::where('organization_id', $this->organizationId)
                ->whereNull('deleted_at')
                ->whereIn('uuid', $uuids)
                ->get();

            // Delete contacts by UUIDs (soft delete)
            foreach($contacts as $contact){

                // Prepare deleted contact for the webhook
                $deletedContacts[] = [
                    'uuid' => $contact->uuid,
                    'deleted_at' => now()->toISOString(),
                ];

                //Mark all unread chats as read
                Chat::where('organization_id', $this->organizationId)
                    ->where('contact_id', $contact->id)
                    ->where('type', 'inbound')
                    ->whereNull('deleted_at')
                    ->where('is_read', 0)
                    ->update([
                        'is_read' => 1
                    ]);
            }

            Contact::whereIn('id', $contacts->pluck('id'))->delete();
        }

    }

    public function assignContactsToGroup(array $contactUuids, string $groupUuid): array
    {
        $contactUuids = array_values(array_unique(array_filter($contactUuids)));
        if (empty($contactUuids)) {
            return [
                'matched_contacts' => 0,
                'new_assignments' => 0,
            ];
        }

        $group = ContactGroup::where('organization_id', $this->organizationId)
            ->where('uuid', $groupUuid)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $contactIds = Contact::where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->whereIn('uuid', $contactUuids)
            ->pluck('id')
            ->toArray();

        if (empty($contactIds)) {
            return [
                'matched_contacts' => 0,
                'new_assignments' => 0,
            ];
        }

        $alreadyAssignedContactIds = ContactContactGroup::where('contact_group_id', $group->id)
            ->whereIn('contact_id', $contactIds)
            ->pluck('contact_id')
            ->toArray();

        $newContactIds = array_values(array_diff($contactIds, $alreadyAssignedContactIds));
        $newAssignments = count($newContactIds);

        if ($newAssignments > 0) {
            $timestamp = now();
            $insertRows = array_map(function ($contactId) use ($group, $timestamp) {
                return [
                    'contact_id' => $contactId,
                    'contact_group_id' => $group->id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }, $newContactIds);

            DB::table('contact_contact_group')->insert($insertRows);
        }

        return [
            'matched_contacts' => count($contactIds),
            'new_assignments' => $newAssignments,
        ];
    }
}
