<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DailySummaryController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\MealController;
use App\Http\Controllers\Api\MealItemController;
use App\Http\Controllers\Api\MealScanController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/meals', [MealController::class, 'index']);
    Route::post('/meals', [MealController::class, 'store']);
    Route::post('/meals/scan', [MealScanController::class, 'scan']);

    Route::get('/meals/{meal}', [MealController::class, 'show']);
    Route::put('/meals/{meal}', [MealController::class, 'update']);
    Route::delete('/meals/{meal}', [MealController::class, 'destroy']);
    Route::post('/meals/{meal}/confirm', [MealController::class, 'confirm']);

    Route::put('/meal-items/{item}', [MealItemController::class, 'update']);
    Route::delete('/meal-items/{item}', [MealItemController::class, 'destroy']);

    Route::get('/daily-summary', [DailySummaryController::class, 'show']);

    Route::get('/goals', [GoalController::class, 'index']);
    Route::put('/goals', [GoalController::class, 'update']);
});
