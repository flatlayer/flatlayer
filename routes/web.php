<?php

use App\Http\Controllers\EntryController;
use App\Http\Controllers\WebhookHandlerController;
use App\Http\Controllers\ImageTransformController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Treat this as a health check
    return response()->json(['message' => 'OK']);
});

// ContentItem routes
Route::get('/entry/{type}', [EntryController::class, 'index'])->name('content.list');
Route::get('/entry/{type}/{slug}', [EntryController::class, 'show'])->name('content.detail');

// Media transform route
Route::get('/image/{id}.{extension}', [ImageTransformController::class, 'transform'])->name('media.transform');

// Webhook route
Route::post('/webhook/{type}', [WebhookHandlerController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('webhook.handle');
