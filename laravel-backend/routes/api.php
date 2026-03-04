<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\ListingImageController;
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

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::apiResource('listings', ListingController::class)->only([
            'store',
            'update',
            'destroy',
        ]);
        Route::post('/listings/{listing}/images', [ListingImageController::class, 'store']);
    });
});
