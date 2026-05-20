<?php

namespace App\Services;

use App\Contracts\QueueProfileContract;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;

class QueueProfileService implements QueueProfileContract
{
    private string $profileName = 'production';

    /**
     * @var array{
     *     connection:string,
     *     cache_store:string,
     *     session_driver:string,
     *     queues:array<int,string>,
     *     workers:array<int,array{name:string,queues:array<int,string>,sleep:int,tries:int,timeout:int}>
     * }
     */
    private array $profile;

    public function __construct(private readonly ConfigRepository $config)
    {
        $configuredProfile = (string) $this->config->get('queue_profile.active', 'production');
        $this->profileName = $configuredProfile !== '' ? $configuredProfile : 'production';

        $profile = $this->config->get("queue_profile.{$this->profileName}");

        if (!is_array($profile)) {
            throw new RuntimeException(__('Queue profile [{$this->profileName}] is not configured.'));
        }

        $defaultConnection = $this->profileName === 'shared' ? 'database' : 'redis';
        $defaultCache = $this->profileName === 'shared' ? 'file' : 'redis';
        $defaultSession = $this->profileName === 'shared' ? 'file' : 'redis';

        $this->profile = [
            'connection' => (string) ($profile['connection'] ?? $defaultConnection),
            'cache_store' => (string) ($profile['cache_store'] ?? $defaultCache),
            'session_driver' => (string) ($profile['session_driver'] ?? $defaultSession),
            'queues' => array_values(array_map('strval', (array) ($profile['queues'] ?? []))),
            'workers' => array_values((array) ($profile['workers'] ?? [])),
        ];
    }

    public function getName(): string
    {
        return $this->profileName;
    }

    public function getConnection(): string
    {
        return $this->profile['connection'];
    }

    public function getCacheStore(): string
    {
        return $this->profile['cache_store'];
    }

    public function getSessionDriver(): string
    {
        return $this->profile['session_driver'];
    }

    public function getQueues(): array
    {
        return $this->profile['queues'];
    }

    public function getWorkers(): array
    {
        $normalized = [];

        foreach ($this->profile['workers'] as $worker) {
            $normalized[] = [
                'name' => (string) ($worker['name'] ?? 'worker'),
                'queues' => array_values(array_map('strval', (array) ($worker['queues'] ?? []))),
                'sleep' => (int) ($worker['sleep'] ?? 1),
                'tries' => (int) ($worker['tries'] ?? 3),
                'timeout' => (int) ($worker['timeout'] ?? 120),
            ];
        }

        return $normalized;
    }

    public function getQueueList(): string
    {
        return implode(',', $this->getQueues());
    }
}
