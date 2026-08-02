<?php

namespace Ghanem\Basata\Tests;

use Ghanem\Basata\BasataServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            BasataServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Basata' => \Ghanem\Basata\Facades\Basata::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('basata.username', 'test-user');
        $app['config']->set('basata.password', 'test-pass');
        $app['config']->set('basata.url', 'https://api.basata.test/');
        $app['config']->set('basata.terminal_id', 'TEST-TERMINAL');
    }
}
