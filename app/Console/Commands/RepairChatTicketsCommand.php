<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\ChatTicketProvisioningService;
use Illuminate\Console\Command;

class RepairChatTicketsCommand extends Command
{
    protected $signature = 'chats:repair-tickets {organization_id? : Limit the repair to one organization}';

    protected $description = 'Create missing chat tickets for contacts that already have chats.';

    public function handle(): int
    {
        $organizationId = $this->argument('organization_id');
        $repaired = 0;

        $query = Contact::query()
            ->selectRaw('contacts.id as id, contacts.organization_id')
            ->whereNull('contacts.deleted_at')
            ->whereExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from('chats')
                    ->whereColumn('chats.contact_id', 'contacts.id')
                    ->whereNull('chats.deleted_at');
            })
            ->whereNotExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from('chat_tickets')
                    ->whereColumn('chat_tickets.contact_id', 'contacts.id');
            })
            ->orderBy('contacts.id');

        if ($organizationId !== null) {
            $query->where('contacts.organization_id', (int) $organizationId);
        }

        $query->chunkById(100, function ($contacts) use (&$repaired) {
            foreach ($contacts as $contact) {
                $ticket = (new ChatTicketProvisioningService((int) $contact->organization_id))
                    ->ensureForContact((int) $contact->id, false);

                if ($ticket !== null) {
                    $repaired++;
                }
            }
        });

        $this->info("Repaired {$repaired} missing chat tickets.");

        return self::SUCCESS;
    }
}
