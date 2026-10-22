<?php

namespace App\Console\Commands;

use App\Services\Campaigns\CampaignDispatchRunner;
use Illuminate\Console\Command;

class DispatchCampaignsCommand extends Command
{
    protected $signature = 'campaigns:dispatch {--batches=20 : Batches of 50 messages at most} {--max-seconds=150 : Stop starting new batches after this many seconds}';

    protected $description = 'Send due campaign messages without a queue worker (shared hosting)';

    public function handle(CampaignDispatchRunner $runner): int
    {
        $processed = $runner->run((int) $this->option('batches'), (int) $this->option('max-seconds'));
        $this->info("Processed {$processed} campaign messages.");

        return self::SUCCESS;
    }
}
