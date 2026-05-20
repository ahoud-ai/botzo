<?php

namespace App\Services\System;

use App\Services\AutomationFlows\AutomationFlowAccessService;
use App\Contracts\QueueProfileContract;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class RuntimeReadinessService
{
    public function __construct(
        private readonly QueueProfileContract $queueProfile,
        private readonly ConfigRepository $config,
        private readonly AutomationFlowAccessService $automationFlowAccess,
    ) {
    }

    /**
     * @return array{status:string,profile:string,checks:array<string,string>,timestamp:string}
     */
    public function evaluate(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'queue_profile' => $this->checkQueueProfileAlignment(),
            'queue_connection' => $this->checkQueueConnection(),
            'cache' => $this->checkCacheStore(),
            'session' => $this->checkSessionDriver(),
            'workers' => $this->checkWorkers(),
            'automation_flows' => $this->checkAutomationFlowSchema(),
        ];

        $ready = collect($checks)->every(fn (string $status): bool => in_array($status, ['up', 'configured'], true));

        return [
            'status' => $ready ? 'ready' : 'not_ready',
            'profile' => $this->queueProfile->getName(),
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
        ];
    }

    private function checkDatabase(): string
    {
        try {
            DB::select('SELECT 1 as ok');

            return 'up';
        } catch (\Throwable $exception) {
            return 'down';
        }
    }

    private function checkQueueProfileAlignment(): string
    {
        $queueMatches = $this->queueProfile->getConnection() === (string) $this->config->get('queue.default');
        $cacheMatches = $this->queueProfile->getCacheStore() === (string) $this->config->get('cache.default');
        $sessionMatches = $this->queueProfile->getSessionDriver() === (string) $this->config->get('session.driver');

        return $queueMatches && $cacheMatches && $sessionMatches ? 'up' : 'mismatch';
    }

    private function checkQueueConnection(): string
    {
        $connection = (string) $this->config->get('queue.default');
        $configured = $this->config->get("queue.connections.{$connection}");

        if (!is_array($configured)) {
            return 'down';
        }

        if ($connection === 'redis') {
            try {
                Redis::connection()->ping();

                return 'up';
            } catch (\Throwable $exception) {
                return 'down';
            }
        }

        if ($connection === 'database') {
            return $this->checkDatabase();
        }

        return 'configured';
    }

    private function checkCacheStore(): string
    {
        $store = (string) $this->config->get('cache.default');
        $configured = $this->config->get("cache.stores.{$store}");

        if (!is_array($configured)) {
            return 'down';
        }

        try {
            $key = 'runtime-readiness:'.uniqid('', true);
            Cache::store($store)->put($key, 'ok', 10);
            $resolved = Cache::store($store)->get($key);
            Cache::store($store)->forget($key);

            return $resolved === 'ok' ? 'up' : 'down';
        } catch (\Throwable $exception) {
            return 'down';
        }
    }

    private function checkSessionDriver(): string
    {
        $driver = (string) $this->config->get('session.driver');

        return match ($driver) {
            'file' => is_writable((string) $this->config->get('session.files')) ? 'up' : 'down',
            'database' => Schema::hasTable((string) $this->config->get('session.table', 'sessions')) ? 'up' : 'down',
            'redis' => $this->checkRedisSessionDriver(),
            'cookie', 'array' => 'configured',
            default => 'configured',
        };
    }

    private function checkRedisSessionDriver(): string
    {
        try {
            Redis::connection()->ping();

            return 'up';
        } catch (\Throwable $exception) {
            return 'down';
        }
    }

    private function checkWorkers(): string
    {
        $queues = $this->queueProfile->getQueues();
        $workers = $this->queueProfile->getWorkers();

        if ($workers === []) {
            return 'down';
        }

        foreach ($workers as $worker) {
            $workerQueues = array_values((array) ($worker['queues'] ?? []));
            if ($workerQueues === []) {
                return 'down';
            }

            foreach ($workerQueues as $queue) {
                if (!in_array((string) $queue, $queues, true)) {
                    return 'down';
                }
            }
        }

        return 'configured';
    }

    private function checkAutomationFlowSchema(): string
    {
        if (!$this->automationFlowAccess->runtimeEnabled()) {
            return 'configured';
        }

        $report = $this->automationFlowAccess->readinessReport();

        return $report['base_schema_ready'] && $report['builder_schema_ready']
            ? 'up'
            : 'down';
    }
}
