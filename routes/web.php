<?php

use App\Http\Controllers\Admin\AdminAccessibilityReportController;
use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminHelpRequestController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\Admin\AdminFlagController;
use App\Http\Controllers\Admin\AdminGovernmentController;
use App\Http\Controllers\Admin\AdminLocationController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminPlaceSubmissionController;
use App\Http\Controllers\Admin\AdminTutorialController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);

    Route::middleware(['isAdmin'])->group(function () {
        Route::get('/me', [AdminAuthController::class, 'me']);

        Route::get('/dashboard', AdminDashboardController::class);

        Route::get('/notifications', [AdminNotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [AdminNotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [AdminNotificationController::class, 'markAllRead']);

        Route::get('/help-requests', [AdminHelpRequestController::class, 'index']);
        Route::get('/help-requests/{id}', [AdminHelpRequestController::class, 'show']);
        Route::put('/help-requests/{id}', [AdminHelpRequestController::class, 'update']);
        Route::post('/help-requests/{id}/resolve', [AdminHelpRequestController::class, 'resolve']);

        Route::get('/tutorials', [AdminTutorialController::class, 'index']);
        Route::post('/tutorials', [AdminTutorialController::class, 'store']);
        Route::put('/tutorials/{id}', [AdminTutorialController::class, 'update']);
        Route::delete('/tutorials/{id}', [AdminTutorialController::class, 'destroy']);

        Route::get('/governments', [AdminGovernmentController::class, 'index']);
        Route::post('/governments', [AdminGovernmentController::class, 'store']);

        Route::get('/locations', [AdminLocationController::class, 'index']);
        Route::get('/locations/{id}', [AdminLocationController::class, 'show']);
        Route::post('/locations', [AdminLocationController::class, 'store']);
        Route::put('/locations/{id}', [AdminLocationController::class, 'update']);
        Route::delete('/locations/{id}', [AdminLocationController::class, 'destroy']);

        Route::put('/locations/{id}/accessibility-report', [AdminAccessibilityReportController::class, 'upsert']);

        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

        Route::get('/place-submissions', [AdminPlaceSubmissionController::class, 'index']);
        Route::post('/place-submissions/{id}/approve', [AdminPlaceSubmissionController::class, 'approve']);
        Route::post('/place-submissions/{id}/reject', [AdminPlaceSubmissionController::class, 'reject']);

        Route::get('/flags', [AdminFlagController::class, 'index']);
        Route::post('/flags/{id}/request-info', [AdminFlagController::class, 'requestInfo']);
        Route::post('/flags/{id}/dismiss', [AdminFlagController::class, 'dismiss']);
        Route::post('/flags/{id}/resolve', [AdminFlagController::class, 'resolve']);

        Route::get('/accounts', [AdminAccountController::class, 'index']);
        Route::post('/accounts/{id}/volunteer/approve', [AdminAccountController::class, 'approveVolunteer']);
        Route::post('/accounts/{id}/volunteer/reject', [AdminAccountController::class, 'rejectVolunteer']);
        Route::post('/accounts', [AdminAccountController::class, 'store']);
        Route::put('/accounts/{id}', [AdminAccountController::class, 'update']);
        Route::delete('/accounts/{id}', [AdminAccountController::class, 'destroy']);
    });
});
