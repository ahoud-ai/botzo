<?php

use App\Http\Controllers\WebWidgetController;
use Illuminate\Support\Facades\Route;

// Public website chat widget: called from customers' sites, guarded by allowed domains and visitor tokens.
Route::middleware('throttle:120,1')->prefix('widget/{key}')->group(function () {
    Route::post('/session', [WebWidgetController::class, 'session']);
    Route::get('/messages', [WebWidgetController::class, 'index']);
    Route::post('/messages', [WebWidgetController::class, 'store']);
    Route::post('/voice/start', [WebWidgetController::class, 'voiceStart']);
    Route::post('/voice/{call}/end', [WebWidgetController::class, 'voiceEnd']);
    Route::post('/voice/{call}/transcript', [WebWidgetController::class, 'voiceTranscript']);
    Route::post('/voice/{call}/tool', [WebWidgetController::class, 'voiceTool']);
});
