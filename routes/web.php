<?php

use App\Http\Controllers\AdminArticleController;
use App\Http\Controllers\AdminCompanyProfileController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminInquiryController;
use App\Http\Controllers\AdminMilestoneController;
use App\Http\Controllers\AdminPortfolioController;
use App\Http\Controllers\AdminProjectController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\AdminServicePackageController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientProjectController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\PortfolioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->file(public_path('index.html')));
Route::get('/brief', fn () => response()->file(public_path('brief.html')));
Route::get('/workspace', fn () => response()->file(public_path('portal.html')));

Route::prefix('api')->group(function () {
    Route::get('/csrf', fn (Request $request) => response()->json(['token' => csrf_token()]));
    Route::get('/portfolios', [PortfolioController::class, 'index']);
    Route::get('/company', [ContentController::class, 'company']);
    Route::get('/services', [ContentController::class, 'services']);
    Route::get('/service-packages', [ContentController::class, 'packages']);
    Route::get('/articles', [ContentController::class, 'articles']);
    Route::get('/articles/{slug}', [ContentController::class, 'article']);
    Route::post('/inquiries', [InquiryController::class, 'store'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth');

    Route::middleware(['auth', 'role:admin,staff'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::apiResource('company-profiles', AdminCompanyProfileController::class)->except('show');
        Route::apiResource('services', AdminServiceController::class)->except('show');
        Route::apiResource('service-packages', AdminServicePackageController::class)->except('show');
        Route::apiResource('articles', AdminArticleController::class)->except('show');
        Route::post('/portfolios', [AdminPortfolioController::class, 'store']);
        Route::put('/portfolios/{portfolio}', [AdminPortfolioController::class, 'update']);
        Route::delete('/portfolios/{portfolio}', [AdminPortfolioController::class, 'destroy']);
        Route::get('/inquiries', [AdminInquiryController::class, 'index']);
        Route::post('/inquiries/{inquiry}/reply', [AdminInquiryController::class, 'reply']);
        Route::get('/projects', [AdminProjectController::class, 'index']);
        Route::get('/clients', [AdminProjectController::class, 'clients']);
        Route::post('/projects', [AdminProjectController::class, 'store']);
        Route::put('/projects/{project}', [AdminProjectController::class, 'update']);
        Route::delete('/projects/{project}', [AdminProjectController::class, 'destroy']);
        Route::get('/projects/{project}/milestones', [AdminMilestoneController::class, 'index']);
        Route::post('/projects/{project}/milestones', [AdminMilestoneController::class, 'store']);
        Route::put('/projects/{project}/milestones/{milestone}', [AdminMilestoneController::class, 'update']);
    });
    Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
    });
    Route::middleware(['auth', 'role:client'])->get('/client/projects', [ClientProjectController::class, 'index']);
});
