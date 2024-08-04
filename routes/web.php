<?php

use App\Http\Controllers\SearchController;
use App\Http\Controllers\SingleModelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{modelSlug}/search', [SearchController::class, 'search'])->name('search');
Route::get('/{modelSlug}/show/{slug}', [SingleModelController::class, 'show'])->name('show');
