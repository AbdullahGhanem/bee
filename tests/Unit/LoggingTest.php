<?php

namespace Ghanem\Basata\Tests\Unit;

use Ghanem\Basata\ApiClient;
use Ghanem\Basata\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoggingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('basata.logging.enabled', true);
        $app['config']->set('basata.cache.enabled', false);
    }

    public function test_logs_request_when_enabled(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true], 200),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Basata API Request'
                    && $context['endpoint'] === 'service'
                    && ! isset($context['params']['login'])
                    && ! isset($context['params']['password']);
            });
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message) => $message === 'Basata API Response');

        $client = new ApiClient();
        $client->request('service', ['action' => 'Test']);
    }

    public function test_logs_error_response(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->once(); // request log
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Basata API Response'
                    && $context['status_code'] === 400;
            });

        $client = new ApiClient();
        $client->request('service', ['action' => 'Test']);
    }

    public function test_does_not_log_credentials(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true], 200),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                if ($message !== 'Basata API Request') {
                    return true;
                }

                return ! isset($context['params']['login'])
                    && ! isset($context['params']['password']);
            });
        Log::shouldReceive('info')->once(); // response log

        $client = new ApiClient();
        $client->request('service', ['action' => 'Test']);
    }

    public function test_does_not_log_when_disabled(): void
    {
        $this->app['config']->set('basata.logging.enabled', false);

        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true], 200),
        ]);

        Log::shouldReceive('channel')->never();
        Log::shouldReceive('info')->never();

        $client = new ApiClient();
        $client->request('service', ['action' => 'Test']);
    }
}
