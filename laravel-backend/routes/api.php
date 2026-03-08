<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\InquiryController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\ListingImageController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json([
    'ok' => true,
]));

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/email/otp/verify', [AuthController::class, 'verifyEmailOtp']);
        Route::post('/email/otp/resend', [AuthController::class, 'resendEmailOtp'])
            ->middleware('throttle:3,1');

        // Password reset (no auth required)
        Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:5,1');
        Route::post('/password/reset', [AuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::get('/listings', [ListingController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites', [FavoriteController::class, 'store']);
        Route::delete('/favorites/{listing}', [FavoriteController::class, 'destroy']);
        Route::post('/listings/{listing}/inquiries', [InquiryController::class, 'store']);
        Route::prefix('inquiries')->group(function (): void {
            Route::get('/sent', [InquiryController::class, 'sent']);
            Route::get('/received', [InquiryController::class, 'received']);
            Route::get('/{inquiry}', [InquiryController::class, 'show']);
        });
        Route::prefix('reports')->group(function (): void {
            Route::post('/listings/{listing}', [ReportController::class, 'storeListing']);
            Route::get('/mine/listings', [ReportController::class, 'mineListings']);
            Route::get('/listings/{postReport}', [ReportController::class, 'showListing']);
            Route::post('/users/{user}', [ReportController::class, 'storeUser']);
            Route::get('/mine/users', [ReportController::class, 'mineUsers']);
            Route::get('/users/{userReport}', [ReportController::class, 'showUser']);
        });
        Route::prefix('admin')->middleware('admin')->group(function (): void {
            Route::prefix('reports')->group(function (): void {
                Route::get('/listings/{postReport}/history', [ReportController::class, 'listingHistory']);
                Route::patch('/listings/{postReport}/status', [ReportController::class, 'updateListingStatus']);
                Route::post('/listings/{postReport}/disable-listing', [ReportController::class, 'disableListing']);
                Route::get('/users/{userReport}/history', [ReportController::class, 'userHistory']);
                Route::patch('/users/{userReport}/status', [ReportController::class, 'updateUserStatus']);
                Route::post('/users/{userReport}/suspend-user', [ReportController::class, 'suspendUser']);
            });
        });
        Route::apiResource('listings', ListingController::class)->only([
            'store',
            'update',
            'destroy',
        ]);
        Route::patch('/inquiries/{inquiry}/decision', [InquiryController::class, 'decide']);
        Route::post('/listings/{listing}/images', [ListingImageController::class, 'store']);
        Route::delete('/listings/{listing}/images/{image}', [ListingImageController::class, 'destroy']);
    });
});
