<?php

namespace App\Services\AutomationFlows;

use App\Mail\CustomEmail;
use App\Models\AutomationFlow;
use App\Models\Contact;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AutomationFlowActionDispatchService
{
    public function __construct(
        private readonly AutomationFlowNodeSecretService $nodeSecrets,
        private readonly AutomationFlowPersonalizationService $personalization,
    ) {
    }

    public function sendEmail(?AutomationFlow $flow, Contact $contact, string $nodeId, array $config): array
    {
        $subject = $this->personalization->replacePlaceholders($contact, (string) Arr::get($config, 'subject', ''));
        $body = nl2br(e($this->personalization->replacePlaceholders($contact, (string) Arr::get($config, 'body', ''))));
        $recipient = (string) ($contact->email ?? '');
        $secret = $flow ? $this->nodeSecrets->payloadForNode($flow, $nodeId, 'send_email') : [];

        if ($recipient === '' || empty($secret)) {
            return [
                'sent' => false,
                'recipient' => $recipient,
                'reason' => $recipient === '' ? 'missing_contact_email' : 'missing_smtp_secret',
            ];
        }

        $mailerConfig = [
            'transport' => 'smtp',
            'host' => (string) Arr::get($secret, 'host'),
            'port' => (int) Arr::get($secret, 'port', 587),
            'username' => (string) Arr::get($secret, 'username'),
            'password' => (string) Arr::get($secret, 'password'),
            'encryption' => (string) Arr::get($secret, 'encryption', 'tls'),
            'timeout' => 10,
            'local_domain' => config('mail.mailers.smtp.local_domain'),
        ];
        $originalFromAddress = config('mail.from.address');
        $originalFromName = config('mail.from.name');
        $originalRuntimeMailer = config('mail.mailers.automation_flow_runtime');

        try {
            Config::set('mail.mailers.automation_flow_runtime', $mailerConfig);
            Config::set('mail.from.address', (string) Arr::get($secret, 'from_email', config('mail.from.address')));
            Config::set('mail.from.name', (string) Arr::get($secret, 'from_name', config('mail.from.name')));

            Mail::mailer('automation_flow_runtime')
                ->to($recipient)
                ->send(new CustomEmail($subject, $body));
        } catch (Throwable $exception) {
            Log::warning('automation_flow_email_failed', [
                'flow_uuid' => $flow?->uuid,
                'node_id' => $nodeId,
                'contact_uuid' => $contact->uuid,
                'reason' => $exception->getMessage(),
            ]);

            return [
                'sent' => false,
                'recipient' => $recipient,
                'reason' => 'smtp_delivery_failed',
                'message' => Str::limit($exception->getMessage(), 180),
            ];
        } finally {
            Config::set('mail.from.address', $originalFromAddress);
            Config::set('mail.from.name', $originalFromName);
            Config::set('mail.mailers.automation_flow_runtime', $originalRuntimeMailer);
            Mail::purge('automation_flow_runtime');
        }

        return [
            'sent' => true,
            'recipient' => $recipient,
            'subject' => $subject,
        ];
    }

}
