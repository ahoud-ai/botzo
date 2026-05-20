<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['web', 'auth:admin'])->group(function () {
    require base_path('routes/web/admin/core.php');
    require base_path('routes/web/admin/languages.php');
    require base_path('routes/web/admin/support.php');
    require base_path('routes/web/admin/settings.php');
    require base_path('routes/web/admin/logs.php');
});
