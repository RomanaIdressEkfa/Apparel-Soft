<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskImageController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [TaskController::class, 'dashboard'])->name('dashboard');

    Route::get('/export/sample-tracking.xlsx', [TaskController::class, 'exportSheet'])->name('tasks.sheet');
    Route::get('/export/tasks.csv', [TaskController::class, 'exportCsv'])->name('tasks.export');
    Route::get('/export/buyers.csv', [BuyerController::class, 'exportCsv'])->name('buyers.export');
    Route::get('/export/updates.csv', [TaskCommentController::class, 'exportCsv'])->name('updates.export');

    Route::middleware(EnsureUserIsAdmin::class)->group(function () {
        Route::get('/api/users', [UserController::class, 'index']);
        Route::patch('/api/users/{user}/approve', [UserController::class, 'approve']);
        Route::patch('/api/users/{user}/revoke', [UserController::class, 'revoke']);
        Route::patch('/api/users/{user}/role', [UserController::class, 'setRole']);
        Route::delete('/api/users/{user}', [UserController::class, 'destroy']);
    });

    Route::get('/api/buyers', [BuyerController::class, 'index']);
    Route::post('/api/buyers', [BuyerController::class, 'store']);
    Route::put('/api/buyers/{buyer}', [BuyerController::class, 'update']);
    Route::delete('/api/buyers/{buyer}', [BuyerController::class, 'destroy']);

    Route::get('/api/tasks', [TaskController::class, 'index']);
    Route::post('/api/tasks', [TaskController::class, 'store']);
    Route::put('/api/tasks/{task}', [TaskController::class, 'update']);
    Route::patch('/api/tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::delete('/api/tasks/{task}', [TaskController::class, 'destroy']);

    Route::get('/api/tasks/{task}/comments', [TaskCommentController::class, 'index']);
    Route::post('/api/tasks/{task}/comments', [TaskCommentController::class, 'store']);

    Route::post('/api/tasks/{task}/images', [TaskImageController::class, 'store']);
    Route::delete('/api/images/{image}', [TaskImageController::class, 'destroy']);
});
