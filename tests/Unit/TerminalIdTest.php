<?php

namespace Ghanem\Basata\Tests\Unit;

use Ghanem\Basata\ApiClient;
use Ghanem\Basata\Exceptions\BasataValidationException;
use Ghanem\Basata\Facades\Basata;
use Ghanem\Basata\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class TerminalIdTest extends TestCase
{
    public function test_terminal_id_comes_from_config_not_a_hardcoded_value(): void
    {
        config()->set('basata.terminal_id', '9876543210');
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        app(ApiClient::class)->getProviderList();

        Http::assertSent(fn ($request) => $request['terminal_id'] === '9876543210');
    }

    public function test_every_action_sends_the_configured_terminal_id(): void
    {
        config()->set('basata.terminal_id', 'T-42');
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
        config()->set('basata.terminal_id', 'DTO-T-42');
        config()->set('basata.cache.enabled', false);
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        Basata::getCategoryListDto();
        Basata::getServiceListDto();
        Basata::getTransactionDto(123);

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['terminal_id'] === 'DTO-T-42');
    }

    public function test_empty_terminal_id_throws_the_documented_validation_exception(): void
    {
        // The global TestCase fixture stubs a non-empty terminal_id so every
        // other test can call the action methods without tripping this
        // guard — which meant this branch had zero coverage. Override it
        // back to empty here to exercise it directly.
        config()->set('basata.terminal_id', '');
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        try {
            app(ApiClient::class)->getAccountInfo();
            $this->fail('Expected BasataValidationException');
        } catch (BasataValidationException $e) {
            $this->assertSame(1024, $e->apiCode);
        }
    }

    public function test_terminal_id_of_zero_string_is_a_legitimate_value(): void
    {
        // empty('0') === true in PHP, so a naive empty() check would wrongly
        // reject a real terminal ID of "0".
        config()->set('basata.terminal_id', '0');
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        app(ApiClient::class)->getAccountInfo();

        Http::assertSent(fn ($request) => $request['terminal_id'] === '0');
    }
}
