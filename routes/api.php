<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/stats', [PublicController::class, 'stats']);
Route::post('/chat', [PublicController::class, 'chat'])->middleware('throttle:30,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:8,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1');

Route::middleware(['auth:sanctum', 'active', 'company'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Espace client : uniquement ses propres pièces et projets.
    Route::middleware('client')->prefix('portal')->group(function () {
        Route::get('/', [PortalController::class, 'overview']);
        Route::get('/quotes/{quote}', [PortalController::class, 'quote']);
        Route::post('/quotes/{quote}/decision', [PortalController::class, 'decide']);
        Route::get('/invoices/{invoice}', [PortalController::class, 'invoice']);
        Route::get('/projects/{project}', [PortalController::class, 'project']);
    });

    // Équipe interne : lecture pour tous, écriture selon le rôle.
    Route::middleware('staff')->group(function () {
        Route::get('/dashboard', DashboardController::class);
        Route::get('/search', SearchController::class);
        Route::get('/settings', [SettingController::class, 'show']);
        Route::get('/team', fn () => \App\Models\User::query()
            ->where('is_active', true)
            ->where('role', '!=', 'client')
            ->orderBy('name')
            ->get(['id', 'name', 'job_title', 'role']));

        Route::get('/companies', [CompanyController::class, 'index']);
        Route::get('/staff', [StaffController::class, 'index']);
        Route::get('/clients', [ClientController::class, 'index']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::get('/quotes', [QuoteController::class, 'index']);
        Route::get('/quotes/{quote}', [QuoteController::class, 'show']);
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);

        Route::middleware('can.do:companies')->group(function () {
            Route::post('/companies', [CompanyController::class, 'store']);
            Route::put('/companies/{company}', [CompanyController::class, 'update']);
            Route::delete('/companies/{company}', [CompanyController::class, 'destroy']);
        });

        Route::middleware('can.do:staff')->group(function () {
            Route::post('/staff', [StaffController::class, 'store']);
            Route::put('/staff/{staff}', [StaffController::class, 'update']);
            Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);
        });

        Route::middleware('can.do:clients')->group(function () {
            Route::post('/clients', [ClientController::class, 'store']);
            Route::put('/clients/{client}', [ClientController::class, 'update']);
            Route::delete('/clients/{client}', [ClientController::class, 'destroy']);
        });

        Route::middleware('can.do:projects')->group(function () {
            Route::post('/projects', [ProjectController::class, 'store']);
            Route::put('/projects/{project}', [ProjectController::class, 'update']);
            Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
            Route::post('/projects/{project}/updates', [ProjectController::class, 'progress']);
        });

        Route::middleware('can.do:quotes')->group(function () {
            Route::post('/quotes', [QuoteController::class, 'store']);
            Route::put('/quotes/{quote}', [QuoteController::class, 'update']);
            Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy']);
            Route::post('/quotes/{quote}/status', [QuoteController::class, 'status']);
        });
        Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert'])->middleware('can.do:quotes.convert');

        Route::middleware('can.do:invoices')->group(function () {
            Route::post('/invoices', [InvoiceController::class, 'store']);
            Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
            Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);
            Route::post('/invoices/{invoice}/status', [InvoiceController::class, 'status']);
            Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'pay']);
        });

        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
            Route::put('/settings', [SettingController::class, 'update']);
            Route::get('/activity', [ActivityController::class, 'index']);
        });
    });
});
