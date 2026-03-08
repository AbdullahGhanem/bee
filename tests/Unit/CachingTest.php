<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\Facades\Bee;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CachingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('bee.cache.enabled', true);
        $app['config']->set('bee.cache.ttl', 3600);
        $app['config']->set('bee.cache.prefix', 'bee_');
    }

    public function test_category_list_is_cached(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => ['categories' => []]], 200),
        ]);

        Bee::getCategoryList();
        Bee::getCategoryList();

        Http::assertSentCount(1);
    }

    public function test_service_list_is_cached(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Bee::getServiceList();
        Bee::getServiceList();

        Http::assertSentCount(1);
    }

    public function test_category_service_list_is_cached(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Bee::getCategoryServiceList();
        Bee::getCategoryServiceList();

        Http::assertSentCount(1);
    }

    public function test_service_input_parameter_list_is_cached(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Bee::getServiceInputParameterList();
        Bee::getServiceInputParameterList();

        Http::assertSentCount(1);
    }

    public function test_service_output_parameter_list_is_cached(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Bee::getServiceOutputParameterList();
        Bee::getServiceOutputParameterList();

        Http::assertSentCount(1);
    }

    public function test_different_languages_cached_separately(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Bee::getCategoryList('en');
        Bee::getCategoryList('ar');

        Http::assertSentCount(2);
    }

    public function test_cache_can_be_disabled(): void
    {
        $this->app['config']->set('bee.cache.enabled', false);

        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Bee::getCategoryList();
        Bee::getCategoryList();

        Http::assertSentCount(2);
    }

    public function test_clear_cache_removes_all_cached_data(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Bee::getCategoryList();
        Bee::clearCache();
        Bee::getCategoryList();

        Http::assertSentCount(2);
    }

    public function test_clear_cache_with_specific_key(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Bee::getCategoryList();
        Bee::getServiceList();

        Bee::clearCache('category_list_en');

        Bee::getCategoryList(); // should make new request
        Bee::getServiceList(); // should still be cached

        Http::assertSentCount(3);
    }

    public function test_transactions_are_not_cached(): void
    {
        Http::fake([
            'https://api.bee.test/report' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        Bee::getTransaction(1);
        Bee::getTransaction(1);

        Http::assertSentCount(2);
    }
}
