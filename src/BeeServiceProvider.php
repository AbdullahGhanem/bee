<?php

namespace Ghanem\Bee;

use Illuminate\Support\ServiceProvider;

class BeeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bee.php', 'bee');

        $this->app->bind('ghanem-bee', function () {
            return new BeeController;
        });

    }
    
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/bee.php' => config_path('bee.php'),
        ], 'config');
    }
}
