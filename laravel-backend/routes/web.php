<?php

use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\ListingModerationController;
use App\Http\Controllers\Admin\InquiryModerationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ReportModerationController;
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

        // Listings
        Route::get('/listings', [ListingModerationController::class, 'index'])->name('admin.listings.index');
        Route::get('/listings/export', [ListingModerationController::class, 'export'])->name('admin.listings.export');
        Route::post('/listings/{listing}/approve', [ListingModerationController::class, 'approve'])->name('admin.listings.approve');
        Route::post('/listings/{listing}/decline', [ListingModerationController::class, 'decline'])->name('admin.listings.decline');

        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('admin.logout');

        // Inquiries
        Route::get('/inquiries', [InquiryModerationController::class, 'index'])->name('admin.inquiries.index');
        Route::get('/inquiries/export', [InquiryModerationController::class, 'export'])->name('admin.inquiries.export');

        // Users
        Route::get('/users', [UserManagementController::class, 'index'])->name('admin.users.index');
        Route::get('/users/export', [UserManagementController::class, 'export'])->name('admin.users.export');
        Route::post('/users/{user}/approve', [UserManagementController::class, 'approve'])->name('admin.users.approve');
        Route::post('/users/{user}/disable', [UserManagementController::class, 'disable'])->name('admin.users.disable');
        Route::post('/users/{user}/enable', [UserManagementController::class, 'enable'])->name('admin.users.enable');

        // Categories
        Route::get('/categories', [CategoryController::class, 'index'])->name('admin.categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('admin.categories.edit');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('admin.categories.update');
        Route::patch('/categories/{category}/toggle-active', [CategoryController::class, 'toggleActive'])->name('admin.categories.toggle-active');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');

        // Reports
        Route::get('/reports', [ReportModerationController::class, 'index'])->name('admin.reports.index');
        Route::get('/reports/export', [ReportModerationController::class, 'export'])->name('admin.reports.export');
        Route::get('/reports/listings/{postReport}', [ReportModerationController::class, 'showListing'])->name('admin.reports.listings.show');
        Route::get('/reports/users/{userReport}', [ReportModerationController::class, 'showUser'])->name('admin.reports.users.show');

        // Report status updates
        Route::patch('/reports/listings/{postReport}/status', [ReportModerationController::class, 'updateListingStatus'])->name('admin.reports.listings.status');
        Route::patch('/reports/users/{userReport}/status', [ReportModerationController::class, 'updateUserStatus'])->name('admin.reports.users.status');

        // Report moderation actions
        Route::post('/reports/listings/{postReport}/disable-listing', [ReportModerationController::class, 'disableListing'])->name('admin.reports.listings.disable');
        Route::post('/reports/users/{userReport}/suspend-user', [ReportModerationController::class, 'suspendUser'])->name('admin.reports.users.suspend');
    });
});