<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\ApiClient;
use Ghanem\Bee\Exceptions\BeeRateLimitException;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('bee.rate_limit.enabled', true);
        $app['config']->set('bee.rate_limit.max_attempts', 3);
        $app['config']->set('bee.cache.enabled', false);
    }

    public function test_allows_requests_within_limit(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true], 200),
        ]);

        $client = new ApiClient();

        $result1 = $client->request('service', ['action' => 'Test']);
        $result2 = $client->request('service', ['action' => 'Test']);

        $this->assertArrayNotHasKey('error', $result1->toArray());
        $this->assertArrayNotHasKey('error', $result2->toArray());
    }

    public function test_blocks_requests_over_limit(): void
    {
        // Regression guard: rate limiting is a business failure like any
        // other API error code, so by default (bee.errors.throw = true) it
        // must throw rather than hand back a "success-shaped" array.
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true], 200),
        ]);

        $client = new ApiClient();

        // Make requests up to the limit
        for ($i = 0; $i < 3; $i++) {
            $client->request('service', ['action' => 'Test']);
        }

        // Next request should be rate limited
        try {
            $client->request('service', ['action' => 'Test']);
            $this->fail('Expected BeeRateLimitException');
        } catch (BeeRateLimitException $e) {
            $this->assertSame(1033, $e->apiCode);
        }
    }

    public function test_blocks_requests_over_limit_without_throwing_when_disabled(): void
    {
        $this->app['config']->set('bee.errors.throw', false);

        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true], 200),
        ]);

        $client = new ApiClient();

        for ($i = 0; $i < 3; $i++) {
            $client->request('service', ['action' => 'Test']);
        }

        $result = $client->request('service', ['action' => 'Test']);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame(1033, $result['code']);
    }

    public function test_rate_limit_does_not_apply_when_disabled(): void
    {
        $this->app['config']->set('bee.rate_limit.enabled', false);

        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true], 200),
        ]);

        $client = new ApiClient();

        for ($i = 0; $i < 5; $i++) {
            $result = $client->request('service', ['action' => 'Test']);
            $this->assertArrayNotHasKey('error', $result->toArray());
        }
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('bee-api');
        parent::tearDown();
    }
}
