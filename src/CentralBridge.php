<?php

namespace Applu\CentralBridge;

use Applu\CentralBridge\Http\Controllers\HealthController;
use Applu\CentralBridge\Http\Controllers\InvoiceController;
use Applu\CentralBridge\Http\Controllers\MetricsController;
use Applu\CentralBridge\Http\Controllers\PaymentController;
use Applu\CentralBridge\Http\Controllers\PaymentEventController;
use Applu\CentralBridge\Http\Controllers\SubscriptionController;
use Applu\CentralBridge\Http\Controllers\UserController;
use Applu\CentralBridge\Http\Middleware\EnforceIdempotency;
use Applu\CentralBridge\Http\Middleware\VerifyCentralSignature;
use Illuminate\Support\Facades\Route;

final class CentralBridge
{
    public const VERSION = '2.1.0';

    public const PER_PAGE = 25;

    /**
     * Middleware that must never run on Bridge routes (the Central sends no
     * session cookie nor CSRF token).
     */
    private const EXCLUDED_MIDDLEWARE = [
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
    ];

    /**
     * Registers every Bridge route. Call it from a routes file (or the
     * `then:` callback of `withRouting` in bootstrap/app.php).
     */
    public static function routes(): void
    {
        Route::prefix(trim((string) config('central-bridge.prefix', 'central-api/v1'), '/'))
            ->middleware(VerifyCentralSignature::class)
            ->withoutMiddleware(self::EXCLUDED_MIDDLEWARE)
            ->name('central-bridge.')
            ->group(function (): void {
                Route::get('health', HealthController::class)->name('health');

                Route::get('payments/{provider}/{paymentId}/owner', [PaymentController::class, 'owner'])
                    ->name('payments.owner');
                Route::get('payments/{provider}/{paymentId}/invoice-request', [PaymentController::class, 'invoiceRequest'])
                    ->name('payments.invoice-request');

                Route::get('subscriptions', SubscriptionController::class)->name('subscriptions');

                Route::get('users', [UserController::class, 'index'])->name('users.index');
                Route::get('users/{ref}', [UserController::class, 'show'])->name('users.show');

                Route::get('metrics', MetricsController::class)->name('metrics');

                Route::middleware(EnforceIdempotency::class)->group(function (): void {
                    Route::post('users/{ref}/plan', [UserController::class, 'changePlan'])->name('users.plan');
                    Route::post('users/{ref}/password-reset', [UserController::class, 'passwordReset'])
                        ->name('users.password-reset');
                    Route::post('users/{ref}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
                    Route::post('users/{ref}/unsuspend', [UserController::class, 'unsuspend'])->name('users.unsuspend');
                    Route::post('invoices', InvoiceController::class)->name('invoices');
                    Route::post('payment-events', PaymentEventController::class)->name('payment-events');
                });
            });
    }
}
