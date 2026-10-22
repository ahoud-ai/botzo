<?php

namespace App\Console\Commands;

use App\Models\AutomationFlowRun;
use App\Services\AutomationFlows\AutomationFlowRuntimeService;
use Illuminate\Console\Command;

class ResumeDelayedFlowsCommand extends Command
{
    protected $signature = 'automation:resume-delayed {--limit=100}';

    protected $description = 'Continue automation flows whose wait step has ended (works with or without a queue worker)';

    public function handle(AutomationFlowRuntimeService $runtime): int
    {
        $runs = AutomationFlowRun::with(['flow', 'version'])
            ->where('status', 'waiting_delay')
            ->whereNotNull('next_resume_at')
            ->where('next_resume_at', '<=', now())
            ->orderBy('next_resume_at')
            ->limit((int) $this->option('limit'))
            ->get();

        // resumeDelayedRun re-checks the run under its contact lock, so a queued resume job and
        // this command never continue the same run twice.
        $runs->each(fn (AutomationFlowRun $run) => $runtime->resumeDelayedRun($run));
        $this->info("Resumed {$runs->count()} automation flow runs.");

        return self::SUCCESS;
    }
}
