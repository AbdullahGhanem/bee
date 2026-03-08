<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\BeeService;
use Ghanem\Bee\Facades\Bee;
use Ghanem\Bee\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_it_registers_the_bee_service(): void
    {
        $service = $this->app->make('ghanem-bee');

        $this->assertInstanceOf(BeeService::class, $service);
    }

    public function test_it_registers_as_singleton(): void
    {
        $service1 = $this->app->make('ghanem-bee');
        $service2 = $this->app->make('ghanem-bee');

        $this->assertSame($service1, $service2);
    }

    public function test_facade_resolves_to_bee_service(): void
    {
        $this->assertInstanceOf(BeeService::class, Bee::getFacadeRoot());
    }

    public function test_config_is_merged(): void
    {
        $this->assertEquals('test-user', config('bee.username'));
        $this->assertEquals('test-pass', config('bee.password'));
        $this->assertEquals('https://api.bee.test/', config('bee.url'));
    }

    public function test_config_can_be_published(): void
    {
        $this->artisan('vendor:publish', [
            '--provider' => 'Ghanem\Bee\BeeServiceProvider',
            '--tag' => 'bee-config',
        ])->assertExitCode(0);
    }
}
