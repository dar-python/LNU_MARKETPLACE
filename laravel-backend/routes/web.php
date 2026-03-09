<?php

use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\ListingModerationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AdminSessionController::class, 'create'])->name('login');
        Route::post('/login', [AdminSessionController::class, 'store'])->name('admin.login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function (): void {
        Route::redirect('/', '/admin/listings');
        Route::get('/listings', [ListingModerationController::class, 'index'])->name('admin.listings.index');
        Route::post('/listings/{listing}/approve', [ListingModerationController::class, 'approve'])
            ->name('admin.listings.approve');
        Route::post('/listings/{listing}/decline', [ListingModerationController::class, 'decline'])
            ->name('admin.listings.decline');
        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('admin.logout');
    });
});
