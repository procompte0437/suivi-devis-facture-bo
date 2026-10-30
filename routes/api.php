<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/stats', [PublicController::class, 'stats']);
Route::post('/chat', [PublicController::class, 'chat'])->middleware('throttle:30,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:8,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1');

Route::middleware(['auth:sanctum', 'active', 'company'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', DashboardController::class);
    Route::get('/search', SearchController::class);
    Route::get('/settings', [SettingController::class, 'show']);
    Route::get('/team', fn () => \App\Models\User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title', 'role']));

    Route::apiResource('companies', CompanyController::class)->except(['show']);
    Route::apiResource('staff', StaffController::class)->except(['show']);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('quotes', QuoteController::class);
    Route::post('/quotes/{quote}/status', [QuoteController::class, 'status']);
    Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert']);
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('/invoices/{invoice}/status', [InvoiceController::class, 'status']);
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'pay']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
        Route::put('/settings', [SettingController::class, 'update']);
        Route::get('/activity', [ActivityController::class, 'index']);
    });
});
