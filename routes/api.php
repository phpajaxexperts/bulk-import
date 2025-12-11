<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductImportController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\ProductController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Product Import Routes
Route::post('/products/import', [ProductImportController::class, 'import']);

// Chunked Upload Routes
Route::prefix('uploads')->group(function () {
    Route::post('/init', [UploadController::class, 'init']);
    Route::post('/{uuid}/chunk', [UploadController::class, 'uploadChunk']);
    Route::post('/{uuid}/complete', [UploadController::class, 'complete']);
    Route::get('/{uuid}/status', [UploadController::class, 'status']);
    Route::get('/{uuid}/resume', [UploadController::class, 'resume']);
});

// Product Routes
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::post('/{id}/attach-image', [ProductController::class, 'attachImage']);
});

// Import Logs
Route::get('/import-logs', [ProductController::class, 'importLogs']);
