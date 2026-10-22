<?php

namespace App\Console\Commands;

use App\Models\WebhookInspectorRequest;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Console\Command;

class RetryWebhooksCommand extends Command
{
    protected $signature = 'webhooks:retry {--prune : Also delete delivery logs older than 14 days}';

    protected $description = 'Retry webhook deliveries that are due, and optionally prune old logs';

    public function handle(WebhookDispatcher $dispatcher): int
    {
        $retried = $dispatcher->retryDue();
        $this->info("Retried {$retried} webhook deliveries.");

        if ($this->option('prune')) {
            $deleted = $dispatcher->prune();
            WebhookInspectorRequest::where('created_at', '<', now()->subDays(7))->delete();
            $this->info("Deleted {$deleted} old webhook deliveries.");
        }

        return self::SUCCESS;
    }
}
