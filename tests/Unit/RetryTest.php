<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\ApiClient;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class RetryTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('bee.retry.tries', 3);
        $app['config']->set('bee.retry.delay', 0);
        $app['config']->set('bee.cache.enabled', false);
    }

    public function test_retries_on_connection_failure(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::sequence()
                ->pushResponse(Http::response(['success' => true, 'data' => []], 200)),
        ]);

        $client = new ApiClient();
        $result = $client->request('service', ['action' => 'Test']);

        $this->assertArrayNotHasKey('error', $result->toArray());
    }

    public function test_returns_response_after_server_error(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $client = new ApiClient();
        $result = $client->request('service', ['action' => 'Test']);

        $this->assertIsArray($result);
        $this->assertEquals(500, $result['status_code']);
    }

    public function test_successful_request_does_not_retry(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true], 200),
        ]);

        $client = new ApiClient();
        $result = $client->request('service', ['action' => 'Test']);

        Http::assertSentCount(1);
    }
}
