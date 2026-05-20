<?php

namespace App\Services\EmbeddedSignup;

use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EmbeddedSignupReviewTestService
{
    private string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('graph.api_version');
    }

    public function buildReport(array $requestedTests = []): array
    {
        $requested = $this->normalizeRequestedTests($requestedTests);
        $config = $this->buildConfigurationState();
        $context = [
            'businesses' => [],
            'business_id' => null,
            'candidate_businesses' => [],
            'waba_accounts' => [],
            'waba_id' => null,
        ];

        $tests = [];

        foreach ($requested as $key) {
            $tests[] = match ($key) {
                'business_management' => $this->runBusinessManagementTest($config, $context),
                'whatsapp_business_management' => $this->runWhatsappBusinessManagementTest($config, $context),
                'whatsapp_business_messaging' => $this->runWhatsappBusinessMessagingTest($config, $context),
                default => $this->buildSkippedTestResult(
                    $key,
                    $this->labelFor($key),
                    '',
                    __('Unsupported Meta review test key.')
                ),
            };
        }

        $counts = [
            'passed' => collect($tests)->where('status', 'passed')->count(),
            'warning' => collect($tests)->where('status', 'warning')->count(),
            'failed' => collect($tests)->where('status', 'failed')->count(),
            'skipped' => collect($tests)->where('status', 'skipped')->count(),
        ];

        return [
            'status' => $counts['failed'] > 0
                ? 'failed'
                : ($counts['warning'] > 0 ? 'warning' : 'passed'),
            'timestamp' => now()->toISOString(),
            'api_version' => $this->apiVersion,
            'requested_tests' => array_values($requested),
            'configuration' => $config,
            'summary' => [
                'total' => count($tests),
                ...$counts,
            ],
            'tests' => $tests,
        ];
    }

    private function runBusinessManagementTest(array $config, array &$context): array
    {
        $endpoint = 'me/businesses?fields=id,name';
        $base = $this->baseTestResult('business_management', $endpoint);

        if (!$config['access_token']) {
            return $this->buildFailedTestResult(
                $base,
                __('Add the saved system user access token before running this test.')
            );
        }

        $response = $this->graphGet('me/businesses', [
            'fields' => 'id,name',
        ]);

        if (!$response->successful()) {
            return $this->buildHttpFailureResult($base, $response, __('Unable to read the businesses list from Meta.'));
        }

        $businesses = (array) $response->json('data', []);
        $context['businesses'] = $businesses;
        $context['business_id'] = data_get($businesses, '0.id');
        $context['candidate_businesses'] = collect($businesses)
            ->map(fn (array $business) => [
                'id' => $business['id'] ?? null,
                'name' => $business['name'] ?? null,
            ])
            ->filter(fn (array $business) => filled($business['id']))
            ->values()
            ->all();

        if (count($businesses) === 0) {
            $candidateBusinesses = $this->resolveBusinessCandidatesFromKnownWabas();
            $context['candidate_businesses'] = $candidateBusinesses;
            $context['businesses'] = collect($candidateBusinesses)
                ->map(fn (array $business) => [
                    'id' => $business['id'] ?? null,
                    'name' => $business['name'] ?? null,
                ])
                ->values()
                ->all();
            $context['business_id'] = data_get($candidateBusinesses, '0.id');

            if (count($candidateBusinesses) === 0) {
                return $this->buildWarningTestResult(
                    $base,
                    __('The request succeeded but did not return any business portfolios.')
                );
            }

            return $this->buildPassedTestResult(
                $base,
                __('The business portfolio was resolved successfully from accessible WhatsApp business accounts when Meta returned an empty businesses list for this system user.'),
                [
                    'business_count' => count($candidateBusinesses),
                    'resolution_strategy' => 'waba_owner_business_info',
                    'sample' => collect($candidateBusinesses)
                        ->take(5)
                        ->map(fn (array $business) => [
                            'id' => $business['id'] ?? null,
                            'name' => $business['name'] ?? null,
                            'source_waba_id' => $business['source_waba_id'] ?? null,
                            'source_waba_name' => $business['source_waba_name'] ?? null,
                        ])
                        ->values()
                        ->all(),
                ]
            );
        }

        return $this->buildPassedTestResult(
            $base,
            __('The businesses list was read successfully from Meta.'),
            [
                'business_count' => count($businesses),
                'sample' => collect($businesses)
                    ->take(5)
                    ->map(fn (array $business) => [
                        'id' => $business['id'] ?? null,
                        'name' => $business['name'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ]
        );
    }

    private function runWhatsappBusinessManagementTest(array $config, array &$context): array
    {
        $endpoint = '{business_id}/owned_whatsapp_business_accounts?fields=id,name,currency,timezone_id';
        $base = $this->baseTestResult('whatsapp_business_management', $endpoint);

        if (!$config['access_token']) {
            return $this->buildFailedTestResult(
                $base,
                __('Add the saved system user access token before running this test.')
            );
        }

        $candidateBusinesses = collect((array) ($context['candidate_businesses'] ?? []));
        $businessIds = collect([$context['business_id'] ?? null])
            ->merge($candidateBusinesses->pluck('id'))
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->values();

        if ($businessIds->isEmpty()) {
            $candidateBusinesses = collect($this->resolveBusinessCandidatesFromKnownWabas());
            $context['candidate_businesses'] = $candidateBusinesses->values()->all();
            $businessIds = collect([$context['business_id'] ?? null])
                ->merge($candidateBusinesses->pluck('id'))
                ->filter(fn ($value) => filled($value))
                ->unique()
                ->values();
        }

        if ($businessIds->isEmpty()) {
            return $this->buildWarningTestResult(
                $base,
                __('Run the business_management test first so the business portfolio can be resolved.')
            );
        }

        $attempts = [];
        $firstFailure = null;
        $hadSuccessfulResponse = false;

        foreach ($businessIds as $candidateBusinessId) {
            $response = $this->graphGet("{$candidateBusinessId}/owned_whatsapp_business_accounts", [
                'fields' => 'id,name,currency,timezone_id',
            ]);

            if (!$response->successful()) {
                $attempts[] = [
                    'business_id' => $candidateBusinessId,
                    'http_status' => $response->status(),
                    'status' => 'failed',
                    'error' => data_get($response->json(), 'error.message'),
                ];

                if ($firstFailure === null) {
                    $firstFailure = $this->buildHttpFailureResult(
                        $base,
                        $response,
                        __('Unable to read owned WhatsApp business accounts from Meta.')
                    );
                }

                continue;
            }

            $hadSuccessfulResponse = true;
            $accounts = (array) $response->json('data', []);
            $attempts[] = [
                'business_id' => $candidateBusinessId,
                'http_status' => $response->status(),
                'status' => 'ok',
                'account_count' => count($accounts),
            ];

            if (count($accounts) === 0) {
                continue;
            }

            $context['business_id'] = $candidateBusinessId;
            $context['waba_accounts'] = $accounts;
            $context['waba_id'] = data_get($accounts, '0.id');

            return $this->buildPassedTestResult(
                $base,
                __('Owned WhatsApp business accounts were read successfully from Meta.'),
                [
                    'business_id' => $candidateBusinessId,
                    'account_count' => count($accounts),
                    'attempts' => $attempts,
                    'sample' => collect($accounts)
                        ->take(5)
                        ->map(fn (array $account) => [
                            'id' => $account['id'] ?? null,
                            'name' => $account['name'] ?? null,
                            'currency' => $account['currency'] ?? null,
                            'timezone_id' => $account['timezone_id'] ?? null,
                        ])
                        ->values()
                        ->all(),
                ]
            );
        }

        if ($hadSuccessfulResponse) {
            return $this->buildWarningTestResult(
                $base,
                __('The request succeeded but no WhatsApp business accounts were returned for the selected business.'),
                [
                    'attempts' => $attempts,
                ]
            );
        }

        if ($firstFailure !== null) {
            $firstFailure['meta']['attempts'] = $attempts;

            return $firstFailure;
        }

        return $this->buildWarningTestResult(
            $base,
            __('Run the business_management test first so the business portfolio can be resolved.')
        );
    }

    private function runWhatsappBusinessMessagingTest(array $config, array &$context): array
    {
        $endpoint = '{waba_id}/phone_numbers?fields=id,display_phone_number,verified_name';
        $base = $this->baseTestResult('whatsapp_business_messaging', $endpoint);

        if (!$config['access_token']) {
            return $this->buildFailedTestResult(
                $base,
                __('Add the saved system user access token before running this test.')
            );
        }

        $wabaId = $context['waba_id'] ?? null;
        if (!$wabaId) {
            return $this->buildWarningTestResult(
                $base,
                __('Run the WhatsApp business management test first so the WhatsApp business account can be resolved.')
            );
        }

        $response = $this->graphGet("{$wabaId}/phone_numbers", [
            'fields' => 'id,display_phone_number,verified_name',
        ]);

        if (!$response->successful()) {
            return $this->buildHttpFailureResult(
                $base,
                $response,
                __('Unable to read phone numbers from the WhatsApp business account.')
            );
        }

        $phoneNumbers = (array) $response->json('data', []);

        if (count($phoneNumbers) === 0) {
            return $this->buildWarningTestResult(
                $base,
                __('The request succeeded but no phone numbers were returned for the selected WhatsApp business account.')
            );
        }

        return $this->buildPassedTestResult(
            $base,
            __('Phone numbers were read successfully from the WhatsApp business account.'),
            [
                'waba_id' => $wabaId,
                'phone_number_count' => count($phoneNumbers),
                'sample' => collect($phoneNumbers)
                    ->take(5)
                    ->map(fn (array $phoneNumber) => [
                        'id' => $phoneNumber['id'] ?? null,
                        'display_phone_number' => $phoneNumber['display_phone_number'] ?? null,
                        'verified_name' => $phoneNumber['verified_name'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ]
        );
    }

    private function graphGet(string $path, array $query = []): Response
    {
        return Http::acceptJson()
            ->timeout(20)
            ->withToken((string) Setting::getValueByKey('whatsapp_access_token'))
            ->get("https://graph.facebook.com/{$this->apiVersion}/{$path}", $query);
    }

    private function resolveBusinessCandidatesFromKnownWabas(): array
    {
        $businesses = [];

        foreach ($this->knownWabaIds() as $wabaId) {
            $response = $this->graphGet($wabaId, [
                'fields' => 'id,name,owner_business_info',
            ]);

            if (!$response->successful()) {
                continue;
            }

            $businessId = data_get($response->json(), 'owner_business_info.id');
            if (!$businessId || isset($businesses[$businessId])) {
                continue;
            }

            $businesses[$businessId] = [
                'id' => $businessId,
                'name' => data_get($response->json(), 'owner_business_info.name'),
                'source_waba_id' => $wabaId,
                'source_waba_name' => $response->json('name'),
            ];
        }

        return array_values($businesses);
    }

    private function knownWabaIds(): array
    {
        return DB::table('organizations')
            ->select(['metadata'])
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->get()
            ->map(function (object $row) {
                $metadata = json_decode((string) ($row->metadata ?? ''), true);

                if (!is_array($metadata)) {
                    return null;
                }

                return data_get($metadata, 'whatsapp.waba_id');
            })
            ->filter(fn ($wabaId) => filled($wabaId))
            ->unique()
            ->values()
            ->all();
    }

    private function buildConfigurationState(): array
    {
        return [
            'app_id' => filled(Setting::getValueByKey('whatsapp_client_id')),
            'app_secret' => filled(Setting::getValueByKey('whatsapp_client_secret')),
            'config_id' => filled(Setting::getValueByKey('whatsapp_config_id')),
            'access_token' => filled(Setting::getValueByKey('whatsapp_access_token')),
        ];
    }

    private function normalizeRequestedTests(array $requestedTests): array
    {
        $allowed = [
            'business_management',
            'whatsapp_business_management',
            'whatsapp_business_messaging',
        ];

        $requested = collect($requestedTests)
            ->flatten()
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->map(fn (string $value) => trim($value))
            ->intersect($allowed)
            ->values()
            ->all();

        return $requested !== [] ? $requested : $allowed;
    }

    private function baseTestResult(string $key, string $endpoint): array
    {
        return [
            'key' => $key,
            'label' => $this->labelFor($key),
            'endpoint' => $endpoint,
            'status' => 'skipped',
            'message' => null,
            'meta' => [],
        ];
    }

    private function labelFor(string $key): string
    {
        return match ($key) {
            'business_management' => 'business_management',
            'whatsapp_business_management' => 'whatsapp_business_management',
            'whatsapp_business_messaging' => 'whatsapp_business_messaging',
            default => $key,
        };
    }

    private function buildPassedTestResult(array $base, string $message, array $meta = []): array
    {
        $base['status'] = 'passed';
        $base['message'] = $message;
        $base['meta'] = $meta;

        return $base;
    }

    private function buildWarningTestResult(array $base, string $message, array $meta = []): array
    {
        $base['status'] = 'warning';
        $base['message'] = $message;
        $base['meta'] = $meta;

        return $base;
    }

    private function buildFailedTestResult(array $base, string $message, array $meta = []): array
    {
        $base['status'] = 'failed';
        $base['message'] = $message;
        $base['meta'] = $meta;

        return $base;
    }

    private function buildSkippedTestResult(string $key, string $label, string $endpoint, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'endpoint' => $endpoint,
            'status' => 'skipped',
            'message' => $message,
            'meta' => [],
        ];
    }

    private function buildHttpFailureResult(array $base, Response $response, string $fallback): array
    {
        return $this->buildFailedTestResult(
            $base,
            data_get($response->json(), 'error.message', $fallback),
            [
                'http_status' => $response->status(),
                'graph_error_code' => data_get($response->json(), 'error.code'),
                'graph_error_subcode' => data_get($response->json(), 'error.error_subcode'),
            ]
        );
    }
}
