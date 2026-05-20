<?php

namespace App\Services\AutomationFlows;

use App\Models\AutomationFlow;
use App\Models\AutomationFlowNodeSecret;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AutomationFlowNodeSecretService
{
    public function sanitizeGraphAndSyncSecrets(AutomationFlow $flow, array $graph, array $nodeSecrets): array
    {
        $sanitized = $this->sanitizeGraph($flow, $graph, $nodeSecrets, true);
        $this->deleteOrphanedSecrets($flow, $sanitized['referenced_secret_keys']);

        return $sanitized['graph'];
    }

    public function sanitizeGraphForValidation(AutomationFlow $flow, array $graph, array $nodeSecrets): array
    {
        $sanitized = $this->sanitizeGraph($flow, $graph, $nodeSecrets, false);

        return [
            'graph' => $sanitized['graph'],
            'node_secrets' => $sanitized['node_secret_payloads'],
        ];
    }

    public function builderPayloadForFlow(AutomationFlow $flow): array
    {
        return $flow->nodeSecrets
            ->keyBy('node_id')
            ->map(function (AutomationFlowNodeSecret $secret) {
                $payload = $secret->payload_json ?? [];

                return [
                    'host' => (string) Arr::get($payload, 'host', ''),
                    'port' => (string) Arr::get($payload, 'port', ''),
                    'username' => (string) Arr::get($payload, 'username', ''),
                    'encryption' => (string) Arr::get($payload, 'encryption', ''),
                    'from_name' => (string) Arr::get($payload, 'from_name', ''),
                    'from_email' => (string) Arr::get($payload, 'from_email', ''),
                    'has_password' => filled(Arr::get($payload, 'password')),
                ];
            })
            ->all();
    }

    public function payloadForNode(AutomationFlow $flow, string $nodeId, string $nodeType = 'send_email'): array
    {
        $secret = $flow->nodeSecrets()
            ->where('node_id', $nodeId)
            ->where('node_type', $nodeType)
            ->first();

        return $secret?->payload_json ?? [];
    }

    public function emailPayloadIsComplete(array $payload): bool
    {
        $host = trim((string) Arr::get($payload, 'host', ''));
        $port = (int) Arr::get($payload, 'port', 0);
        $username = trim((string) Arr::get($payload, 'username', ''));
        $password = (string) Arr::get($payload, 'password', '');
        $fromEmail = trim((string) Arr::get($payload, 'from_email', ''));
        $encryption = strtolower(trim((string) Arr::get($payload, 'encryption', 'tls')));

        return $host !== ''
            && $port >= 1
            && $port <= 65535
            && $username !== ''
            && $password !== ''
            && filter_var($fromEmail, FILTER_VALIDATE_EMAIL) !== false
            && in_array($encryption, ['tls', 'ssl', 'starttls', 'none'], true);
    }

    /**
     * @return array<string, string>
     */
    public function duplicateAll(AutomationFlow $sourceFlow, AutomationFlow $targetFlow, ?array $graph = null): array
    {
        $sourceFlow->loadMissing('nodeSecrets');
        $activeSecrets = $this->activeSecretsForGraph($sourceFlow, $graph ?? ($sourceFlow->graph_json ?? []));

        return $activeSecrets
            ->mapWithKeys(function (AutomationFlowNodeSecret $secret) use ($targetFlow): array {
                $duplicate = AutomationFlowNodeSecret::create([
                    'uuid' => (string) Str::uuid(),
                    'automation_flow_id' => $targetFlow->id,
                    'organization_id' => $targetFlow->organization_id,
                    'node_id' => $secret->node_id,
                    'node_type' => $secret->node_type,
                    'payload_json' => $secret->payload_json,
                ]);

                return [$secret->node_id . ':' . $secret->node_type => $duplicate->uuid];
            })
            ->all();
    }

    private function sanitizeGraph(AutomationFlow $flow, array $graph, array $nodeSecrets, bool $persist): array
    {
        $existingSecrets = $flow->nodeSecrets()
            ->get()
            ->keyBy(fn (AutomationFlowNodeSecret $secret) => $secret->node_id . ':' . $secret->node_type);

        $resolvedSecrets = [];
        $referencedSecretKeys = [];

        $graph['nodes'] = collect(Arr::get($graph, 'nodes', []))
            ->map(function (array $node) use (
                $flow,
                $nodeSecrets,
                $existingSecrets,
                $persist,
                &$resolvedSecrets,
                &$referencedSecretKeys
            ) {
                if (($node['type'] ?? null) !== 'send_email') {
                    unset($node['config']['smtp']);

                    return $node;
                }

                $nodeId = (string) ($node['id'] ?? '');
                $existing = $existingSecrets->get($nodeId . ':send_email');
                $payload = $this->mergeEmailPayload(
                    $existing?->payload_json ?? [],
                    Arr::get($nodeSecrets, $nodeId, [])
                );

                if (!empty($payload)) {
                    $resolvedSecrets[$nodeId] = $payload;

                    if ($persist) {
                        $secret = $existing ?: new AutomationFlowNodeSecret([
                            'uuid' => (string) Str::uuid(),
                            'automation_flow_id' => $flow->id,
                            'organization_id' => $flow->organization_id,
                            'node_id' => $nodeId,
                            'node_type' => 'send_email',
                        ]);

                        $secret->payload_json = $payload;
                        $secret->save();

                        $node['config']['secret_ref'] = $secret->uuid;
                        $referencedSecretKeys[] = $secret->node_id . ':' . $secret->node_type;
                    } else {
                        $node['config']['secret_ref'] = (string) ($existing?->uuid ?: Arr::get($node, 'config.secret_ref', ''));
                    }
                }

                unset($node['config']['smtp']);

                return $node;
            })
            ->values()
            ->all();

        return [
            'graph' => $graph,
            'node_secret_payloads' => $resolvedSecrets,
            'referenced_secret_keys' => array_values(array_unique($referencedSecretKeys)),
        ];
    }

    private function deleteOrphanedSecrets(AutomationFlow $flow, array $referencedSecretKeys): void
    {
        $flow->nodeSecrets()
            ->where('node_type', 'send_email')
            ->get()
            ->each(function (AutomationFlowNodeSecret $secret) use ($referencedSecretKeys): void {
                $secretKey = $secret->node_id . ':' . $secret->node_type;

                if (!in_array($secretKey, $referencedSecretKeys, true)) {
                    $secret->delete();
                }
            });
    }

    private function activeSecretsForGraph(AutomationFlow $flow, array $graph): Collection
    {
        $references = collect(Arr::get($graph, 'nodes', []))
            ->filter(fn (array $node) => ($node['type'] ?? null) === 'send_email' && filled($node['id']))
            ->mapWithKeys(fn (array $node) => [
                (string) $node['id'] . ':send_email' => (string) Arr::get($node, 'config.secret_ref', ''),
            ]);

        return $flow->nodeSecrets
            ->filter(function (AutomationFlowNodeSecret $secret) use ($references): bool {
                $key = $secret->node_id . ':' . $secret->node_type;

                if (!$references->has($key)) {
                    return false;
                }

                $expectedUuid = (string) $references->get($key, '');

                return $expectedUuid === '' || $expectedUuid === $secret->uuid;
            })
            ->values();
    }

    private function mergeEmailPayload(array $existing, array $incoming): array
    {
        $payload = $existing;

        foreach (['host', 'port', 'username', 'encryption', 'from_name', 'from_email'] as $key) {
            if (!Arr::has($incoming, $key)) {
                continue;
            }

            $value = is_string(Arr::get($incoming, $key))
                ? trim((string) Arr::get($incoming, $key))
                : Arr::get($incoming, $key);

            if ($value !== '' && $value !== null) {
                $payload[$key] = $value;
            }
        }

        if (Arr::has($incoming, 'password') && filled(Arr::get($incoming, 'password'))) {
            $payload['password'] = (string) Arr::get($incoming, 'password');
        }

        if (isset($payload['port'])) {
            $payload['port'] = (int) $payload['port'];
        }

        if (isset($payload['encryption'])) {
            $payload['encryption'] = strtolower(trim((string) $payload['encryption']));
        }

        return $payload;
    }
}
