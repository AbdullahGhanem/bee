<?php

namespace Ghanem\Basata\Tests\Unit;

use Ghanem\Basata\Facades\Basata;
use Ghanem\Basata\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CachingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('basata.cache.enabled', true);
        $app['config']->set('basata.cache.ttl', 3600);
        $app['config']->set('basata.cache.prefix', 'basata_');
    }

    public function test_category_list_is_cached(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true, 'data' => ['categories' => []]], 200),
        ]);

        Basata::getCategoryList();
        Basata::getCategoryList();

        Http::assertSentCount(1);
    }

    public function test_service_list_is_cached(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Basata::getServiceList();
        Basata::getServiceList();

        Http::assertSentCount(1);
    }

    public function test_category_service_list_is_cached(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Basata::getCategoryServiceList();
        Basata::getCategoryServiceList();

        Http::assertSentCount(1);
    }

    public function test_service_input_parameter_list_is_cached(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Basata::getServiceInputParameterList();
        Basata::getServiceInputParameterList();

        Http::assertSentCount(1);
    }

    public function test_service_output_parameter_list_is_cached(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Basata::getServiceOutputParameterList();
        Basata::getServiceOutputParameterList();

        Http::assertSentCount(1);
    }

    public function test_provider_list_is_cached(): void
    {
        // GetProviderList sends service_version: 0 = "force update the service
        // list", which FAQ A1 says not to do routinely.
        Http::fake([
            'https://api.basata.test/service' => Http::response([
                'success' => true,
                'data' => ['service_version' => 3],
            ], 200),
        ]);

        Basata::getProviderList();
        Basata::getProviderList();

        Http::assertSentCount(1);
    }

    public function test_different_languages_cached_separately(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Basata::getCategoryList('en');
        Basata::getCategoryList('ar');

        Http::assertSentCount(2);
    }

    public function test_cache_can_be_disabled(): void
    {
        $this->app['config']->set('basata.cache.enabled', false);

        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Basata::getCategoryList();
        Basata::getCategoryList();

        Http::assertSentCount(2);
    }

    public function test_clear_cache_removes_all_cached_data(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Basata::getCategoryList();
        Basata::clearCache();
        Basata::getCategoryList();

        Http::assertSentCount(2);
    }

    public function test_clear_cache_with_specific_key(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Basata::getCategoryList();
        Basata::getServiceList();

        Basata::clearCache('category_list_en');

        Basata::getCategoryList(); // should make new request
        Basata::getServiceList(); // should still be cached

        Http::assertSentCount(3);
    }

    public function test_a_business_failure_response_is_not_cached(): void
    {
        // Regression guard: with throwing disabled, request() returns a
        // plain array for a business failure. cached() must not store it —
        // otherwise a transient error (e.g. rate limit, 1034) would poison
        // every read of that list for the full TTL.
        $this->app['config']->set('basata.errors.throw', false);

        Http::fake([
            'https://api.basata.test/service' => Http::response(['success' => false, 'code' => 2000], 200),
        ]);

        Basata::getCategoryList();
        Basata::getCategoryList();

        Http::assertSentCount(2);
    }

    public function test_transactions_are_not_cached(): void
    {
        Http::fake([
            'https://api.basata.test/report' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Basata::getTransaction(1);
        Basata::getTransaction(1);

        Http::assertSentCount(2);
    }
}
