<?php

namespace App\Services;

use App\Http\Resources\ChatNoteResource;
use App\Models\ChatLog;
use App\Models\ChatNote;
use App\Models\Contact;

class ChatNoteService
{
    private function currentOrganizationId(): int
    {
        return (int) session()->get('current_organization');
    }

    private function scopedContact(string $uuid): Contact
    {
        return Contact::where('organization_id', $this->currentOrganizationId())
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    private function scopedNote(string $uuid): ChatNote
    {
        return ChatNote::where('uuid', $uuid)
            ->whereHas('contact', function ($query) {
                $query->where('organization_id', $this->currentOrganizationId())
                    ->whereNull('deleted_at');
            })
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    public function get(object $request)
    {
        $rows = (new ChatNote)->listAll($request->query('search'));

        return ChatNoteResource::collection($rows);
    }

    public function getByUuid($uuid = null)
    {
        return ChatNote::where('id', $uuid)
            ->whereHas('contact', function ($query) {
                $query->where('organization_id', $this->currentOrganizationId())
                    ->whereNull('deleted_at');
            })
            ->first();
    }

    public function store(object $request, $uuid = NULL)
    {
        $contact = $this->scopedContact($request->contact);

        $note = $uuid === null ? new ChatNote() : $this->scopedNote($uuid);
        $note->contact_id = $contact->id;
        $note->content = $request->notes;
        $note->created_by = auth()->user()->id;
        $note->save();

        ChatLog::insert([
            'contact_id' => $contact->id,
            'entity_type' => 'notes',
            'entity_id' => $note->id,
            'created_at' => now()
        ]);

        return $note;
    }

    public function delete($uuid)
    {
        $note = $this->scopedNote($uuid);
        $note->deleted_at = date('Y-m-d H:i:s');
        $note->deleted_by = auth()->user()->id;
        $note->save();
    } 
}
