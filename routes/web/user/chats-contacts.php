<?php

use Illuminate\Support\Facades\Route;

Route::get('/chats/get-all-uuids', [App\Http\Controllers\User\ChatController::class, 'getAllUuids']);
Route::post('/chats/update-sort-direction', [App\Http\Controllers\User\ChatController::class, 'updateChatSortDirection']);
Route::post('/chats/bulk-action', [App\Http\Controllers\User\ChatController::class, 'bulkAction']);
Route::get('/chats/{uuid?}', [App\Http\Controllers\User\ChatController::class, 'index']);
Route::get('/chats/{id}/media', [App\Http\Controllers\User\ChatController::class, 'getMedia']);
Route::post('/chats', [App\Http\Controllers\User\ChatController::class, 'sendMessage']);
Route::delete('/chats/{uuid}', [App\Http\Controllers\User\ChatController::class, 'deleteChats']);
Route::post('/chat/{uuid}/send/template', [App\Http\Controllers\User\ChatController::class, 'sendTemplateMessage']);
Route::get('/chats/{contactId}/messages', [App\Http\Controllers\User\ChatController::class, 'loadMoreMessages']);

Route::get('/tickets/{status}', [App\Http\Controllers\User\ChatTicketController::class, 'index']);
Route::put('/tickets/{uuid}/update', [App\Http\Controllers\User\ChatTicketController::class, 'update']);
Route::put('/tickets/{uuid}/assign', [App\Http\Controllers\User\ChatTicketController::class, 'assign']);

Route::get('/contacts/{uuid?}', [App\Http\Controllers\User\ContactController::class, 'index'])->name('contacts');
Route::post('/contacts', [App\Http\Controllers\User\ContactController::class, 'store']);
Route::post('/contacts/import', [App\Http\Controllers\User\ContactController::class, 'import']);
Route::post('/contacts/assign-group', [App\Http\Controllers\User\ContactController::class, 'assignGroup']);
Route::post('/contacts/{uuid}', [App\Http\Controllers\User\ContactController::class, 'update']);
Route::put('/contacts/favorite/{uuid}', [App\Http\Controllers\User\ContactController::class, 'favorite']);
Route::delete('/contacts', [App\Http\Controllers\User\ContactController::class, 'delete']);

Route::get('/contact-groups/{uuid?}', [App\Http\Controllers\User\ContactGroupController::class, 'index']);
Route::post('/contact-groups', [App\Http\Controllers\User\ContactGroupController::class, 'store']);
Route::post('/contact-groups/import', [App\Http\Controllers\User\ContactGroupController::class, 'import']);
Route::post('/contact-groups/{uuid}', [App\Http\Controllers\User\ContactGroupController::class, 'update']);
Route::delete('/contact-groups', [App\Http\Controllers\User\ContactGroupController::class, 'delete']);
