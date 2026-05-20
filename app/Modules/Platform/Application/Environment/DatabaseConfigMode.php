<?php

namespace App\Modules\Platform\Application\Environment;

class DatabaseConfigMode
{
    public function enabled(): bool
    {
        return (bool) config('platform.enable_database_config', false);
    }
}
