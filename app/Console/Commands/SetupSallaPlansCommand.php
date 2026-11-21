<?php

namespace App\Console\Commands;

use App\Services\Salla\SallaPlans;
use Illuminate\Console\Command;

class SetupSallaPlansCommand extends Command
{
    protected $signature = 'salla:setup-plans {--sync : Also give existing plans the agreed prices and limits}';

    protected $description = 'Create the plans used by Salla stores. Existing plans change only with --sync';

    public function handle(SallaPlans $plans): int
    {
        $result = $plans->setup((bool) $this->option('sync'));
        $this->info("Created {$result['created']} plans, updated {$result['updated']}.");

        return self::SUCCESS;
    }
}
