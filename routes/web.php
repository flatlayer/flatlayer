<?php

use App\Http\Controllers\WebhookHandlerController;
use App\Http\Controllers\MediaTransformController;
use App\Http\Controllers\ModelListController;
use App\Http\Controllers\ModelDetailController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{modelSlug}/list', [ModelListController::class, 'index'])->name('list');
Route::get('/{modelSlug}/show/{slug}', [ModelDetailController::class, 'show'])->name('show');
Route::get('/media/{id}.{extension}', [MediaTransformController::class, 'transform'])->name('media.transform');

Route::post('/{modelSlug}/webhook', [WebhookHandlerController::class, 'handle'])
    ->middleware('throttle:10,1');
