<?php

namespace App\Console\Commands;

use App\Services\Salla\SallaPlans;
use Illuminate\Console\Command;

class SetupSallaPlansCommand extends Command
{
    protected $signature = 'salla:setup-plans';

    protected $description = 'Create the plans used by Salla stores. Plans that already exist are left as they are';

    public function handle(SallaPlans $plans): int
    {
        $this->info('Created '.$plans->setup().' plans.');

        return self::SUCCESS;
    }
}
