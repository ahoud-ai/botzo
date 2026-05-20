<?php

use App\Http\Controllers\User\AutomationFlowController;
use App\Http\Controllers\User\CannedReplyController;
use Illuminate\Support\Facades\Route;

Route::get('/automation/basic', [CannedReplyController::class, 'index'])->name('cannedReply');
Route::get('/automation/basic/create', [CannedReplyController::class, 'create'])->name('cannedReply.create');
Route::post('/automation/basic', [CannedReplyController::class, 'store'])->name('cannedReply.store');
Route::get('/automation/basic/{uuid}/edit', [CannedReplyController::class, 'edit'])->name('cannedReply.edit');
Route::put('/automation/basic/{uuid}', [CannedReplyController::class, 'update'])->name('cannedReply.update');
Route::delete('/automation/basic/{uuid}', [CannedReplyController::class, 'delete'])->name('cannedReply.destroy');

Route::get('/automation/flows', [AutomationFlowController::class, 'index'])->name('flowbuilder.index');
Route::post('/automation/flows', [AutomationFlowController::class, 'store'])->name('flowbuilder.store');
Route::get('/automation/flows/{uuid}', [AutomationFlowController::class, 'show'])->name('flowbuilder.show');
Route::put('/automation/flows/{uuid}', [AutomationFlowController::class, 'update'])->name('flowbuilder.update');
Route::post('/automation/flows/{uuid}/autosave', [AutomationFlowController::class, 'autosave'])->name('flowbuilder.autosave');
Route::post('/automation/flows/{uuid}/validate', [AutomationFlowController::class, 'validateDraft'])->name('flowbuilder.validate');
Route::post('/automation/flows/{uuid}/publish', [AutomationFlowController::class, 'publish'])->name('flowbuilder.publish');
Route::post('/automation/flows/{uuid}/preview', [AutomationFlowController::class, 'preview'])->name('flowbuilder.preview');
Route::post('/automation/flows/{uuid}/pause', [AutomationFlowController::class, 'pause'])->name('flowbuilder.pause');
Route::post('/automation/flows/{uuid}/duplicate', [AutomationFlowController::class, 'duplicate'])->name('flowbuilder.duplicate');
Route::post('/automation/flows/{uuid}/assets', [AutomationFlowController::class, 'uploadAsset'])->name('flowbuilder.assets.store');
Route::delete('/automation/flows/{uuid}/assets/{assetUuid}', [AutomationFlowController::class, 'deleteAsset'])->name('flowbuilder.assets.destroy');
Route::delete('/automation/flows/{uuid}', [AutomationFlowController::class, 'destroy'])->name('flowbuilder.destroy');
