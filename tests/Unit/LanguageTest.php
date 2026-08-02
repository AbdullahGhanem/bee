<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\ApiClient;
use Ghanem\Bee\Facades\Bee;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * Mirrors TerminalIdTest: language had the identical bug — BeeService (and
 * the Bee facade it backs) hardcoded 'en' as a literal parameter default on
 * every method, so config('bee.language') was unreachable for any caller
 * that omitted $lang. BASATA_LANGUAGE=ar would silently do nothing.
 */
class LanguageTest extends TestCase
{
    public function test_language_comes_from_config_when_omitted(): void
    {
        config()->set('bee.language', 'ar');
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        app(ApiClient::class)->getProviderList();

        Http::assertSent(fn ($request) => $request['language'] === 'ar');
    }

    public function test_facade_calls_send_the_configured_language_not_a_hardcoded_en(): void
    {
        config()->set('bee.language', 'ar');
        config()->set('bee.cache.enabled', false);
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        Bee::getCategoryList();
        Bee::getServiceList();
        Bee::getAccountInfo();

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['language'] === 'ar');
    }

    public function test_dto_methods_send_the_configured_language(): void
    {
        config()->set('bee.language', 'ar');
        config()->set('bee.cache.enabled', false);
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        Bee::getCategoryListDto();
        Bee::getServiceListDto();
        Bee::getTransactionDto(123);

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['language'] === 'ar');
    }

    public function test_an_explicit_language_argument_still_overrides_config(): void
    {
        config()->set('bee.language', 'ar');
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        Bee::getCategoryList('en');

        Http::assertSent(fn ($request) => $request['language'] === 'en');
    }
}
