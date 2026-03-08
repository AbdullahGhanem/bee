<?php

namespace Ghanem\Bee;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BeeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bee.php', 'bee');

        $this->app->singleton('ghanem-bee', function () {
            return new BeeService();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/bee.php' => config_path('bee.php'),
        ], 'bee-config');

        $this->registerWebhookRoute();
    }

    protected function registerWebhookRoute(): void
    {
        if (! config('bee.webhook.enabled', false)) {
            return;
        }

        Route::middleware(config('bee.webhook.middleware', ['api']))
            ->post(config('bee.webhook.path', 'bee/webhook'), [Http\BeeWebhookController::class, 'handle'])
            ->name('bee.webhook');
    }
}
