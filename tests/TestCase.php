<?php

namespace Ghanem\Bee\Tests;

use Ghanem\Bee\BeeServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            BeeServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Bee' => \Ghanem\Bee\Facades\Bee::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('bee.username', 'test-user');
        $app['config']->set('bee.password', 'test-pass');
        $app['config']->set('bee.url', 'https://api.bee.test/');
    }
}
