<?php

namespace Ghanem\Bee;

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
    }
}
