<?php

use App\Http\Controllers\NamePredictionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('app'));

Route::prefix('api')->group(function () {
    Route::get('/lookup', [NamePredictionController::class, 'lookup']);
    Route::get('/predictions', [NamePredictionController::class, 'index']);
    Route::post('/predictions', [NamePredictionController::class, 'store']);
    Route::delete('/predictions/{prediction}', [NamePredictionController::class, 'destroy']);
});
