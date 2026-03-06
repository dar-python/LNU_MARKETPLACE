<?php

use App\Http\Controllers\Api\V1\AuthController;
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
});