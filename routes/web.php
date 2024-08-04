<?php

use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\SingleModelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{modelSlug}/list', [ListController::class, 'index'])->name('list');
Route::get('/{modelSlug}/show/{slug}', [SingleModelController::class, 'show'])->name('show');

// The update webhook
Route::post('/{modelSlug}/webhook', [GitHubWebhookController::class, 'handle']);

Route::get('/media/{id}.{extension}', [ImageController::class, 'transform'])
    ->name('media.transform');
