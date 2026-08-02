<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\ApiClient;
use Ghanem\Bee\Facades\Bee;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class TerminalIdTest extends TestCase
{
    public function test_terminal_id_comes_from_config_not_a_hardcoded_value(): void
    {
        config()->set('bee.terminal_id', '9876543210');
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        app(ApiClient::class)->getProviderList();

        Http::assertSent(fn ($request) => $request['terminal_id'] === '9876543210');
    }

    public function test_every_action_sends_the_configured_terminal_id(): void
    {
        config()->set('bee.terminal_id', 'T-42');
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $client = app(ApiClient::class);
        $client->getProviderList();
        $client->getServiceList();
        $client->getCategoryList();

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['terminal_id'] === 'T-42');
    }

    public function test_dto_methods_send_the_configured_terminal_id(): void
    {
        config()->set('bee.terminal_id', 'DTO-T-42');
        config()->set('bee.cache.enabled', false);
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        Bee::getCategoryListDto();
        Bee::getServiceListDto();
        Bee::getTransactionDto(123);

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['terminal_id'] === 'DTO-T-42');
    }
}
