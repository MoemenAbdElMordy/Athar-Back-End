<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccessibilityContributionController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DataExportRequestController;
use App\Http\Controllers\Api\FlagController;
use App\Http\Controllers\Api\GovernmentController;
use App\Http\Controllers\Api\HelpRequestController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\PlaceSubmissionController;
use App\Http\Controllers\Api\PrivacySettingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\TutorialController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\VolunteerController;
use App\Http\Controllers\Api\VolunteerAnalyticsController;
use Illuminate\Support\Facades\Route;

// ─── Payment callback (public, no auth) ──────────────────
Route::post('/payments/paymob/callback', [PaymentController::class, 'callback']);

Route::middleware('json.accept')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'registerUser']);
    Route::post('/auth/register-user', [AuthController::class, 'registerUser']);
    Route::post('/auth/register-volunteer', [AuthController::class, 'registerVolunteer']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/governments', [GovernmentController::class, 'index']);
    Route::get('/tutorials', [TutorialController::class, 'index']);
    Route::post('/tutorials/{id}/view', [TutorialController::class, 'trackView']);
    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/locations/nearby', [LocationController::class, 'nearby']);
    Route::get('/locations/{id}', [LocationController::class, 'show']);
    Route::get('/locations/{id}/ratings', [RatingController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::put('/auth/password', [AuthController::class, 'changePassword']);
        Route::get('/auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('/auth/sessions/others', [AuthController::class, 'destroyOtherSessions']);
        Route::delete('/auth/sessions/{id}', [AuthController::class, 'destroySession']);

        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
        Route::get('/profile/stats', [ProfileController::class, 'stats']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);

        Route::get('/privacy/settings', [PrivacySettingController::class, 'show']);
        Route::put('/privacy/settings', [PrivacySettingController::class, 'update']);
        Route::post('/privacy/data-export', [DataExportRequestController::class, 'store']);
        Route::get('/privacy/data-export/{id}', [DataExportRequestController::class, 'show']);

        Route::post('/support/tickets', [SupportTicketController::class, 'store']);
        Route::delete('/account', [AccountController::class, 'destroy']);

        Route::post('/locations/{id}/ratings', [RatingController::class, 'store']);

        Route::post('/place-submissions', [PlaceSubmissionController::class, 'store']);
        Route::get('/place-submissions/mine', [PlaceSubmissionController::class, 'mine']);

        Route::post('/flags', [FlagController::class, 'store']);
        Route::post('/locations/{id}/flags', [FlagController::class, 'storeForLocation']);
        Route::get('/flags/mine', [FlagController::class, 'mine']);

        Route::put('/locations/{id}/accessibility-report', [AccessibilityContributionController::class, 'upsert']);

        // ─── Payments ─────────────────────────────────────
        Route::post('/payments/card/checkout', [PaymentController::class, 'cardCheckout']);
        Route::post('/payments/wallet/checkout', [PaymentController::class, 'walletCheckout']);
        Route::post('/payments/{id}/refresh', [PaymentController::class, 'refresh']);
        Route::get('/payments/{id}', [PaymentController::class, 'show']);

        Route::get('/help-requests/{id}/messages', [HelpRequestController::class, 'messages']);
        Route::post('/help-requests/{id}/messages', [HelpRequestController::class, 'storeMessage']);

        Route::middleware('api.role:user')->group(function (): void {
            Route::post('/locations/report', [LocationController::class, 'storeReport']);
            Route::post('/help-requests', [HelpRequestController::class, 'store']);
            Route::get('/help-requests/mine', [HelpRequestController::class, 'mine']);
            Route::post('/help-requests/{id}/pay', [HelpRequestController::class, 'payForService']);
            Route::post('/help-requests/{id}/cancel', [HelpRequestController::class, 'cancel']);
            Route::post('/help-requests/{id}/rate', [HelpRequestController::class, 'rateVolunteer']);
        });

        Route::middleware('api.role:volunteer')->group(function (): void {
            Route::post('/help-requests/{id}/accept', [HelpRequestController::class, 'accept']);
            Route::post('/help-requests/{id}/decline', [HelpRequestController::class, 'decline']);
            Route::post('/help-requests/{id}/complete', [HelpRequestController::class, 'complete']);

            Route::post('/volunteer/status', [VolunteerController::class, 'status']);
            Route::get('/volunteer/incoming', [VolunteerController::class, 'incoming']);
            Route::get('/volunteer/active', [VolunteerController::class, 'active']);
            Route::get('/volunteer/history', [VolunteerController::class, 'history']);
            Route::get('/volunteer/impact', [VolunteerController::class, 'impact']);

            Route::prefix('volunteer/analytics')->group(function (): void {
                Route::get('/earnings', [VolunteerAnalyticsController::class, 'earnings']);
                Route::get('/performance', [VolunteerAnalyticsController::class, 'performance']);
                Route::get('/reviews', [VolunteerAnalyticsController::class, 'reviews']);
            });
        });
    });
});
