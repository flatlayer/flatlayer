<?php

use App\Http\Controllers\WebhookHandlerController;
use App\Http\Controllers\MediaTransformController;
use App\Http\Controllers\EntryListController;
use App\Http\Controllers\EntryDetailController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ContentItem routes
Route::get('/content/{type}', [EntryListController::class, 'index'])->name('content.list');
Route::get('/content/{type}/{slug}', [EntryDetailController::class, 'show'])->name('content.detail');

// Media transform route
Route::get('/media/{id}.{extension}', [MediaTransformController::class, 'transform'])->name('media.transform');

// Webhook route
Route::post('/webhook/{type}', [WebhookHandlerController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('webhook.handle');
