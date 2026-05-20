<?php

namespace App\Console\Commands;

use App\Contracts\QueueProfileContract;
use Illuminate\Console\Command;

class SystemQueueProfileCommand extends Command
{
    protected $signature = 'system:queue-profile {--format=text : Output format (text|json)}';

    protected $description = 'Show the active queue profile used by runbooks and release gates.';

    public function handle(QueueProfileContract $profile): int
    {
        $payload = [
            'profile' => $profile->getName(),
            'connection' => $profile->getConnection(),
            'cache_store' => $profile->getCacheStore(),
            'session_driver' => $profile->getSessionDriver(),
            'queues' => $profile->getQueues(),
            'workers' => $profile->getWorkers(),
        ];

        if (strtolower((string) $this->option('format')) === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Queue profile');
        $this->line(" - profile: {$payload['profile']}");
        $this->line(" - connection: {$payload['connection']}");
        $this->line(" - cache store: {$payload['cache_store']}");
        $this->line(" - session driver: {$payload['session_driver']}");
        $this->line(' - queues: '.implode(', ', $payload['queues']));
        $this->line(' - workers:');

        foreach ($payload['workers'] as $worker) {
            $this->line(
                sprintf(
                    '   * %s => queues=[%s], sleep=%d, tries=%d, timeout=%d',
                    $worker['name'],
                    implode(',', $worker['queues']),
                    $worker['sleep'],
                    $worker['tries'],
                    $worker['timeout']
                )
            );
        }

        return self::SUCCESS;
    }
}
