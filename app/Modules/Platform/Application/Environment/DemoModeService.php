<?php

namespace App\Modules\Platform\Application\Environment;

class DemoModeService
{
    public function enabled(): bool
    {
        return (bool) config('platform.demo_mode', false);
    }
}
