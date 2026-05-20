<?php

namespace App\Services\AutomationFlows;

use App\Models\Contact;
use App\Models\Organization;

class AutomationFlowPersonalizationService
{
    public function replacePlaceholders(Contact $contact, string $message): string
    {
        $organization = Organization::find($contact->organization_id);
        $metadata = $contact->metadata ? json_decode($contact->metadata, true) : [];

        $payload = [
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'full_name' => trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')),
            'email' => $contact->email,
            'phone' => $contact->phone,
            'organization_name' => $organization?->name,
        ];

        foreach ($metadata as $key => $value) {
            $payload[strtolower(str_replace(' ', '_', $key))] = $value;
        }

        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($payload) {
            return $payload[$matches[1]] ?? $matches[0];
        }, $message);
    }
}
