<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Event;
use Illuminate\Notifications\Events\NotificationSent as LaravelNotificationSent;
use App\Listeners\SendRealtimeNotification;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
