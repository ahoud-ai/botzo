<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StorePaymentGateway;
use App\Http\Resources\PaymentGatewayResource;
use App\Models\PaymentGateway;
use App\Modules\Platform\Application\Environment\DemoModeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class PaymentGatewayController extends BaseController
{
    public function index(Request $request)
    {
        $rows = PaymentGateway::query()
            ->whereRaw('LOWER(name) = ?', ['moyasar'])
            ->paginate(10);

        return Inertia::render('Admin/Setting/PaymentGateway', ['rows' => PaymentGatewayResource::collection($rows)]);
    }

    public function show(Request $request, $type)
    {
        $gateway = $this->resolveGatewayByType($type);

        if (! $gateway) {
            if (! $request->expectsJson() && ! $request->wantsJson()) {
                return redirect('/admin/payment-gateways')->with(
                    'status',
                    [
                        'type' => 'error',
                        'message' => __('The selected payment processor is not supported.'),
                    ]
                );
            }

            return response()->json([
                'success' => false,
                'message' => __('The selected payment processor is not supported.'),
            ], 404);
        }

        if (! $request->expectsJson() && ! $request->wantsJson()) {
            return Inertia::render('Admin/Setting/PaymentGatewayMoyasar', [
                'gateway' => $this->normalizeMoyasarGateway($gateway),
            ]);
        }

        return response()->json(['success' => true, 'data' => $gateway]);
    }

    public function update(StorePaymentGateway $request, $type)
    {
        if (app(DemoModeService::class)->enabled()) {
            // Return a response indicating that the function is not allowed in demo environment
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('Updating settings is not allowed in demo.'),
                ]
            );
        }

        $gateway = $this->resolveGatewayByType($type);

        if (! $gateway) {
            return Redirect::back()->with(
                'status',
                [
                    'type' => 'error',
                    'message' => __('The selected payment processor is not supported.'),
                ]
            );
        }

        $metadata = $this->buildMoyasarMetadata($request, $gateway);

        $gateway->update([
            'metadata' => json_encode($metadata),
            'is_active' => $request->status,
        ]);

        $redirectUrl = ! $request->expectsJson() && ! $request->wantsJson()
            ? '/admin/payment-gateways/moyasar'
            : '/admin/payment-gateways';

        return redirect($redirectUrl)->with(
            'status', [
                'type' => 'success',
                'message' => ucfirst((string) $gateway->name).' updated successfully!',
            ]
        );
    }

    private function resolveGatewayByType(string $type): ?PaymentGateway
    {
        return PaymentGateway::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($type)])
            ->whereRaw('LOWER(name) = ?', ['moyasar'])
            ->first();
    }

    private function normalizeMoyasarGateway(PaymentGateway $gateway): array
    {
        $metadata = $this->decodeMetadata($gateway->metadata);
        $activeMode = strtolower((string) ($metadata['active_mode'] ?? $metadata['mode'] ?? 'test'));
        $activeMode = in_array($activeMode, ['test', 'live'], true) ? $activeMode : 'test';

        return [
            'name' => $gateway->name,
            'is_active' => (int) $gateway->is_active,
            'active_mode' => $activeMode,
            'test' => $this->resolveMoyasarEnvironmentConfig($metadata, 'test', $activeMode),
            'live' => $this->resolveMoyasarEnvironmentConfig($metadata, 'live', $activeMode),
        ];
    }

    private function resolveMoyasarEnvironmentConfig(array $metadata, string $environment, string $activeMode): array
    {
        $environmentConfig = $metadata[$environment] ?? null;

        if (is_array($environmentConfig)) {
            return [
                'publishable_key' => (string) ($environmentConfig['publishable_key'] ?? ''),
                'secret_key' => (string) ($environmentConfig['secret_key'] ?? ''),
                'webhook_secret' => (string) ($environmentConfig['webhook_secret'] ?? ''),
            ];
        }

        if ($environment === $activeMode) {
            return [
                'publishable_key' => (string) ($metadata['publishable_key'] ?? ''),
                'secret_key' => (string) ($metadata['secret_key'] ?? ''),
                'webhook_secret' => (string) ($metadata['webhook_secret'] ?? ''),
            ];
        }

        return [
            'publishable_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
        ];
    }

    private function buildMoyasarMetadata(StorePaymentGateway $request, PaymentGateway $gateway): array
    {
        $current = $this->normalizeMoyasarGateway($gateway);
        $activeMode = strtolower((string) ($request->input('active_mode') ?: $request->input('mode', $current['active_mode'] ?? 'test')));
        $activeMode = in_array($activeMode, ['test', 'live'], true) ? $activeMode : 'test';

        $structuredPayload = $request->has('test') || $request->has('live') || $request->has('active_mode');

        $test = $current['test'] ?? $this->emptyMoyasarEnvironmentConfig();
        $live = $current['live'] ?? $this->emptyMoyasarEnvironmentConfig();

        if ($structuredPayload) {
            if ($request->has('test')) {
                $test = $this->sanitizeMoyasarEnvironmentConfig((array) $request->input('test', []));
            }

            if ($request->has('live')) {
                $live = $this->sanitizeMoyasarEnvironmentConfig((array) $request->input('live', []));
            }
        } else {
            $target = $this->sanitizeMoyasarEnvironmentConfig([
                'publishable_key' => $request->input('publishable_key'),
                'secret_key' => $request->input('secret_key'),
                'webhook_secret' => $request->input('webhook_secret'),
            ]);

            if ($activeMode === 'live') {
                $live = $target;
            } else {
                $test = $target;
            }
        }

        $activeConfig = $activeMode === 'live' ? $live : $test;

        return [
            'active_mode' => $activeMode,
            'mode' => $activeMode,
            'test' => $test,
            'live' => $live,
            'publishable_key' => $activeConfig['publishable_key'] !== '' ? $activeConfig['publishable_key'] : null,
            'secret_key' => $activeConfig['secret_key'] !== '' ? $activeConfig['secret_key'] : null,
            'webhook_secret' => $activeConfig['webhook_secret'] !== '' ? $activeConfig['webhook_secret'] : null,
        ];
    }

    private function sanitizeMoyasarEnvironmentConfig(array $config): array
    {
        return [
            'publishable_key' => trim((string) ($config['publishable_key'] ?? '')),
            'secret_key' => trim((string) ($config['secret_key'] ?? '')),
            'webhook_secret' => trim((string) ($config['webhook_secret'] ?? '')),
        ];
    }

    private function emptyMoyasarEnvironmentConfig(): array
    {
        return [
            'publishable_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
        ];
    }

    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata) || trim($metadata) === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }
}
