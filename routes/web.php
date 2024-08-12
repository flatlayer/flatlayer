<?php

use App\Http\Controllers\ContentController;
use App\Http\Controllers\ImageTransformController;
use App\Http\Controllers\WebhookHandlerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'OK',
    ]);
});

// ContentItem routes
Route::get('/entry/{type}', [ContentController::class, 'index'])->name('entry.list');
Route::get('/entry/{type}/{slug}', [ContentController::class, 'show'])->name('entry.detail');

// Media transform route
Route::get('/image/{id}.{extension}', [ImageTransformController::class, 'transform'])->name('image.transform');
Route::get('/image/{id}/metadata', [ImageTransformController::class, 'metadata'])->name('image.metadata');

// Webhook route
Route::post('/webhook/{type}', [WebhookHandlerController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('webhook.handle');
