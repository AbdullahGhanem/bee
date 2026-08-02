<?php

namespace Ghanem\Basata;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BasataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/basata.php', 'basata');

        $this->app->singleton('ghanem-basata', function () {
            return new BasataService();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/basata.php' => config_path('basata.php'),
        ], 'basata-config');

        $this->registerWebhookRoute();
    }

    protected function registerWebhookRoute(): void
    {
        if (! config('basata.webhook.enabled', false)) {
            return;
        }

        Route::middleware(config('basata.webhook.middleware', ['api']))
            ->post(config('basata.webhook.path', 'basata/webhook'), [Http\BasataWebhookController::class, 'handle'])
            ->name('basata.webhook');
    }
}
