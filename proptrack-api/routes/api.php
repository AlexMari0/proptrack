<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Properties CRUD
    Route::apiResource('properties', PropertyController::class);

    // Property photos
    Route::post('properties/{property}/photos', [PropertyController::class, 'uploadPhoto']);
    Route::delete('properties/{property}/photos/{mediaId}', [PropertyController::class, 'deletePhoto']);

    // Tenants CRUD
    Route::apiResource('tenants', TenantController::class);

    // Contracts CRUD + custom actions
    Route::apiResource('contracts', ContractController::class)->except(['destroy']);
    Route::post('contracts/{contract}/terminate', [ContractController::class, 'terminate']);
    Route::get('contracts/{contract}/document', [ContractController::class, 'document']);
});

