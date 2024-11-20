<?php

use App\Http\Controllers\HierarchyController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\ShowController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'OK',
    ]);
});

// Entry routes
Route::get('/entry/{type}', [ListController::class, 'index'])->name('entry.list');
Route::get('/entry/batch/{type}', [ShowController::class, 'batch'])->name('entry.batch');
Route::get('/entry/{type}/{slug}', [ShowController::class, 'show'])
    ->where('slug', '.*')
    ->name('entry.show');

// Hierarchy routes
Route::get('/hierarchy/{type}', [HierarchyController::class, 'index'])->name('hierarchy.index');
Route::get('/hierarchy/{type}/{path}', [HierarchyController::class, 'find'])
    ->where('path', '.*')
    ->name('hierarchy.find');

// Image transform route
Route::get('/image/{id}.{extension}', [ImageController::class, 'transform'])->name('image.transform');
Route::get('/image/{id}/metadata', [ImageController::class, 'metadata'])->name('image.metadata');

// Webhook route
Route::post('/webhook/{type}', [WebhookController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('webhook.handle');
