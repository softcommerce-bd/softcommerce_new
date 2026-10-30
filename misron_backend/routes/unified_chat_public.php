<?php

use App\Http\Controllers\ChatWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhook/{platform}', [ChatWebhookController::class, 'verify']);
Route::post('webhook/{platform}', [ChatWebhookController::class, 'direct']);
Route::post('gateway/ingest', [ChatWebhookController::class, 'gateway']);

// Outgoing attachments only: the provider downloads them through a short-lived signed link.
Route::get('media/{message}/{index}', [ChatWebhookController::class, 'media'])->middleware('signed')->name('chat.media');
