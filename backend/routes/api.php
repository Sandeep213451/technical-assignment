<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AssignmentController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user/profile', [AuthController::class, 'profile']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    
    // Task Management
    Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
    Route::apiResource('tasks', TaskController::class);
    
    // Assignment Engine
    Route::get('/tasks/{id}/eligible-users', [AssignmentController::class, 'getEligibleUsers']);
    Route::get('/my-eligible-tasks', [AssignmentController::class, 'getMyEligibleTasks']);
    Route::post('/tasks/recompute-eligibility', [AssignmentController::class, 'recomputeEligibility']);
});

