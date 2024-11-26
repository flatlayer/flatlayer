<?php

use App\Http\Controllers\HierarchyController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\ShowController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes follow a consistent pattern for entry operations:
| - /entries/{type}/show : Root entry for type
| - /entries/{type}/show/{path} : Specific entry
| - /entries/{type}/list : List entries
| - /entries/{type}/batch : Batch retrieve entries
|
*/

// Health check
Route::get('/', function () {
    return response()->json([
        'status' => 'OK',
        'version' => '1.0',
    ]);
});

// Content entries
Route::prefix('entries')->name('entries.')->group(function () {
    // List entries
    Route::get('{type}/list', [ListController::class, 'index'])->name('list');

    // Batch retrieve entries
    Route::get('{type}/batch', [ShowController::class, 'batch'])->name('batch');

    // Show single entry (both root and specific paths)
    Route::get('{type}/show/{path?}', [ShowController::class, 'show'])
        ->where('path', '[a-zA-Z0-9\-_/]*')
        ->name('show');

    // Hierarchy routes
    Route::get('{type}/hierarchy', [HierarchyController::class, 'index'])->name('hierarchy');

    // Remove path filters in hierarchy until we fix groupByParent in ContentHierarchy class
//    Route::get('{type}/hierarchy/{path}', [HierarchyController::class, 'find'])
//        ->where('path', '[a-zA-Z0-9\-_/]*')
//        ->name('hierarchy.find');
});

// Image management
Route::prefix('images')->name('images.')->group(function () {
    // Transform image
    Route::get('{id}.{extension}', [ImageController::class, 'transform'])->name('transform');

    // Get image metadata
    Route::get('{id}/metadata', [ImageController::class, 'metadata'])->name('metadata');
});

// Webhooks
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    // Handle repository webhooks
    Route::post('{type}', [WebhookController::class, 'handle'])
        ->middleware('throttle:10,1')
        ->name('handle');
});
