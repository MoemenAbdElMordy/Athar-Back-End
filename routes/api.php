<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FlagController;
use App\Http\Controllers\Api\HelpRequestController;
use App\Http\Controllers\Api\PlaceSubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/place-submissions', [PlaceSubmissionController::class, 'store']);
    Route::post('/flags', [FlagController::class, 'store']);

    Route::post('/help-requests', [HelpRequestController::class, 'store']);
});
