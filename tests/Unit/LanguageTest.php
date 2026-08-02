<?php

namespace Ghanem\Basata\Tests\Unit;

use Ghanem\Basata\ApiClient;
use Ghanem\Basata\Facades\Basata;
use Ghanem\Basata\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * Mirrors TerminalIdTest: language had the identical bug — BasataService (and
 * the Basata facade it backs) hardcoded 'en' as a literal parameter default on
 * every method, so config('basata.language') was unreachable for any caller
 * that omitted $lang. BASATA_LANGUAGE=ar would silently do nothing.
 */
class LanguageTest extends TestCase
{
    public function test_language_comes_from_config_when_omitted(): void
    {
        config()->set('basata.language', 'ar');
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        app(ApiClient::class)->getProviderList();

        Http::assertSent(fn ($request) => $request['language'] === 'ar');
    }

    public function test_facade_calls_send_the_configured_language_not_a_hardcoded_en(): void
    {
        config()->set('basata.language', 'ar');
        config()->set('basata.cache.enabled', false);
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        Basata::getCategoryList();
        Basata::getServiceList();
        Basata::getAccountInfo();

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['language'] === 'ar');
    }

    public function test_dto_methods_send_the_configured_language(): void
    {
        config()->set('basata.language', 'ar');
        config()->set('basata.cache.enabled', false);
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        Basata::getCategoryListDto();
        Basata::getServiceListDto();
        Basata::getTransactionDto(123);

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['language'] === 'ar');
    }

    public function test_an_explicit_language_argument_still_overrides_config(): void
    {
        config()->set('basata.language', 'ar');
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        Basata::getCategoryList('en');

        Http::assertSent(fn ($request) => $request['language'] === 'en');
    }
}
