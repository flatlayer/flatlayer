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
Route::get('/content/{type}', [ContentController::class, 'index'])->name('content.list');
Route::get('/content/{type}/{slug}', [ContentController::class, 'show'])->name('content.detail');

// Media transform route
Route::get('/image/{id}.{extension}', [ImageTransformController::class, 'transform'])->name('image.transform');

// Webhook route
Route::post('/webhook/{type}', [WebhookHandlerController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('webhook.handle');
