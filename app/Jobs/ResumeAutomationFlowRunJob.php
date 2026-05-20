<?php

namespace App\Jobs;

use App\Models\AutomationFlowRun;
use App\Services\AutomationFlows\AutomationFlowRuntimeService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResumeAutomationFlowRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $queue = 'default';

    public function __construct(
        public readonly int $runId,
    ) {
    }

    public function handle(AutomationFlowRuntimeService $runtimeService): void
    {
        $run = AutomationFlowRun::with(['flow', 'version'])->find($this->runId);

        if ($run) {
            $runtimeService->resumeDelayedRun($run);
        }
    }
}
