<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Routes عامة (بدون مصادقة)
Route::get('/plans', [PlanController::class, 'index']);
Route::get('/plans/{id}', [PlanController::class, 'show']);

// Routes المصادقة
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

 Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
     Route::post('/plans', [PlanController::class, 'store']);
    Route::put('/plans/{id}', [PlanController::class, 'update']);
    Route::delete('/plans/{id}', [PlanController::class, 'destroy']);
    
    // إدارة الاشتراكات
    Route::get('/subscriptions/my', [SubscriptionController::class, 'mySubscription']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::get('/subscriptions', [SubscriptionController::class, 'all']);
    Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show']);
    Route::post('/subscriptions/{id}/renew', [SubscriptionController::class, 'renew']);
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/subscriptions/{id}/retry-payment', [SubscriptionController::class, 'retryPayment']);
});