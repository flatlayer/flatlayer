<?php

use App\Http\Controllers\ListController;
use App\Http\Controllers\ShowController;
use App\Http\Controllers\ImageTransformController;
use App\Http\Controllers\WebhookHandlerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'OK',
    ]);
});

// Entry routes
Route::get('/entry/{type}', [ListController::class, 'index'])->name('entry.list');
Route::get('/entry/batch/{type}', [ShowController::class, 'batch'])->name('entry.batch');
Route::get('/entry/{type}/{slug}', [ShowController::class, 'show'])->name('entry.show');

// Image transform route
Route::get('/image/{id}.{extension}', [ImageTransformController::class, 'transform'])->name('image.transform');
Route::get('/image/{id}/metadata', [ImageTransformController::class, 'metadata'])->name('image.metadata');

// Webhook route
Route::post('/webhook/{type}', [WebhookHandlerController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('webhook.handle');
