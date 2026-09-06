<?php

use App\Http\Controllers\AdminInquiryController;
use App\Http\Controllers\AdminMilestoneController;
use App\Http\Controllers\AdminPortfolioController;
use App\Http\Controllers\AdminProjectController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientProjectController;
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
    Route::post('/inquiries', [InquiryController::class, 'store'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth');

    Route::middleware(['auth', 'role:admin,staff'])->prefix('admin')->group(function () {
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
