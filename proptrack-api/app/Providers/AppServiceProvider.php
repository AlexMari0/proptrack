<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Event;
use Illuminate\Notifications\Events\NotificationSent as LaravelNotificationSent;
use App\Listeners\SendRealtimeNotification;
use App\Payments\MidtransGateway;
use App\Payments\PaymentGateway;
use App\Payments\SimulatedGateway;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Payment simulation must be switched on explicitly and is never used in production.
        $this->app->bind(PaymentGateway::class, function () {
            $simulate = config('services.midtrans.simulate') === true
                && ! $this->app->isProduction()
                && ! config('services.midtrans.is_production');

            return $simulate
                ? new SimulatedGateway()
                : new MidtransGateway(
                    (string) config('services.midtrans.server_key', ''),
                    (bool) config('services.midtrans.is_production', false),
                );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            LaravelNotificationSent::class,
            SendRealtimeNotification::class
        );
    }
}
