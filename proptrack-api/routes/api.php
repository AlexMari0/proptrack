<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

// ─── Public webhook endpoints (no auth) ──────────────────────────────────────
Route::prefix('v1/payments/webhook')->group(function () {
    Route::post('/midtrans', [PaymentController::class, 'midtransWebhook']);
    Route::post('/xendit',   [PaymentController::class, 'xenditWebhook']);
});

Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
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

    // Invoices (read-only + send notification + PDF download)
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
    Route::get('invoices/{invoice}/document', [InvoiceController::class, 'document']);

    // Payments (authenticated)
    Route::post('payments/create-transaction', [PaymentController::class, 'createTransaction']);
    Route::get('payments/{invoice}/status', [PaymentController::class, 'status']);

    // Financial Reports
    Route::get('reports/financial', [ReportController::class, 'financial']);
    Route::get('reports/financial/export', [ReportController::class, 'export']);
    Route::get('reports/financial/{property}', [ReportController::class, 'propertyFinancial']);

    // Complaint Tickets
    Route::apiResource('tickets', TicketController::class);
    Route::put('tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
    Route::post('tickets/{ticket}/comments', [TicketController::class, 'storeComment']);

    // Notifications
    Route::get('notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::put('notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'readAll']);
    Route::put('notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'read']);
});

