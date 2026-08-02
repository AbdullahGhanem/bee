<?php

namespace Ghanem\Basata\Tests\Unit;

use Ghanem\Basata\BasataService;
use Ghanem\Basata\Facades\Basata;
use Ghanem\Basata\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_it_registers_the_basata_service(): void
    {
        $service = $this->app->make('ghanem-basata');

        $this->assertInstanceOf(BasataService::class, $service);
    }

    public function test_it_registers_as_singleton(): void
    {
        $service1 = $this->app->make('ghanem-basata');
        $service2 = $this->app->make('ghanem-basata');

        $this->assertSame($service1, $service2);
    }

    public function test_facade_resolves_to_basata_service(): void
    {
        $this->assertInstanceOf(BasataService::class, Basata::getFacadeRoot());
    }

    public function test_config_is_merged(): void
    {
        $this->assertEquals('test-user', config('basata.username'));
        $this->assertEquals('test-pass', config('basata.password'));
        $this->assertEquals('https://api.basata.test/', config('basata.url'));
    }

    public function test_config_can_be_published(): void
    {
        $this->artisan('vendor:publish', [
            '--provider' => 'Ghanem\Basata\BasataServiceProvider',
            '--tag' => 'basata-config',
        ])->assertExitCode(0);
    }
}
