<?php

use App\Http\Controllers\ChatAccountController;
use App\Http\Controllers\ChatAnalyticsController;
use App\Http\Controllers\UnifiedChatController;
use Illuminate\Support\Facades\Route;

// Loaded under /admin/unified-chat with the admin middleware stack (see UnifiedChatServiceProvider).
// Authorization is enforced in the controllers through ChatAccess; permission middleware here is the coarse gate.
Route::get('/', [UnifiedChatController::class, 'index'])->name('unified-chat.index')->middleware('permission:view_unified_chat');

Route::prefix('api')->controller(UnifiedChatController::class)->group(function () {
    Route::get('bootstrap', 'bootstrap');
    Route::get('updates', 'updates');
    Route::get('conversations', 'conversations');
    Route::get('conversations/{id}', 'show')->whereNumber('id');
    Route::get('conversations/{id}/messages', 'messages')->whereNumber('id');
    Route::post('conversations/{id}/messages', 'send')->whereNumber('id')->middleware('throttle:60,1');
    Route::post('conversations/{id}/messages/{messageId}/retry', 'retry');
    Route::get('conversations/{id}/templates', 'templates')->whereNumber('id');
    Route::post('conversations/{id}/template-messages', 'sendTemplate')->whereNumber('id')->middleware('throttle:30,1');
    Route::post('conversations/{id}/read', 'markRead');
    Route::post('conversations/{id}', 'update');
    Route::post('conversations/{id}/assign', 'assign');
    Route::post('conversations/{id}/notes', 'addNote');
    Route::post('conversations/{id}/notes/{noteId}', 'editNote');
    Route::delete('conversations/{id}/notes/{noteId}', 'editNote');
    Route::post('conversations/{id}/customer', 'linkCustomer');
    Route::get('conversations/{id}/orders', 'orders');
    Route::post('conversations/{id}/orders', 'createOrder')->middleware('throttle:20,1');
    Route::get('customers', 'searchCustomers');
    Route::get('products', 'searchProducts');
    Route::get('districts', 'districts');
    Route::get('saved-replies', 'savedReplies');
    Route::post('saved-replies/{id?}', 'saveReply');
    Route::delete('saved-replies/{id}', 'deleteReply');
    Route::get('settings', 'settings');
    Route::post('settings', 'saveSettings');
});
Route::get('attachments/{messageId}/{index}', [UnifiedChatController::class, 'attachment'])->name('unified-chat.attachment');

Route::get('api/analytics', [ChatAnalyticsController::class, 'report']);
Route::get('export', [ChatAnalyticsController::class, 'export'])->name('unified-chat.export');

Route::prefix('api/accounts')->controller(ChatAccountController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('connect/whatsapp-manual', 'manualWhatsApp');
    Route::post('connect/{platform}', 'connect');
    Route::post('{id}/verify', 'verify')->whereNumber('id');
    Route::post('{id}', 'update');
    Route::delete('{id}', 'destroy');
});
Route::get('connect/callback', [ChatAccountController::class, 'callback'])->name('unified-chat.callback');
