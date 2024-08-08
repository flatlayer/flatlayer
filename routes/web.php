<?php

use App\Http\Controllers\WebhookHandlerController;
use App\Http\Controllers\MediaTransformController;
use App\Http\Controllers\ContentItemListController;
use App\Http\Controllers\ContentItemDetailController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{modelSlug}/list', [ContentItemListController::class, 'index'])->name('list');
Route::get('/{modelSlug}/show/{slug}', [ContentItemDetailController::class, 'show'])->name('show');
Route::get('/media/{id}.{extension}', [MediaTransformController::class, 'transform'])->name('media.transform');

Route::post('/{modelSlug}/webhook', [WebhookHandlerController::class, 'handle'])
    ->middleware('throttle:10,1');
