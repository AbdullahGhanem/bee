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
        $this->app['config']->set('basata.errors.throw', false);

        Http::fake([
            'https://api.basata.test/service' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->once(); // request log
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                // The error payload echoes back the request params — they must
                // be redacted there too, not just in the request log.
                return $message === 'Basata API Response'
                    && $context['status_code'] === 400
                    && ! isset($context['response']['params']['login'])
                    && ! isset($context['response']['params']['password']);
            });

        $client = new ApiClient();
        $client->request('service', ['action' => 'Test']);
    }

    public function test_error_payload_returned_to_the_caller_carries_no_credentials(): void
    {
        $this->app['config']->set('basata.errors.throw', false);
        $this->app['config']->set('basata.logging.enabled', false);

        Http::fake([
            'https://api.basata.test/service' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $result = (new ApiClient())->request('service', ['action' => 'Test']);

        $this->assertArrayNotHasKey('login', $result['params']);
        $this->assertArrayNotHasKey('password', $result['params']);
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

    public function test_masks_voucher_secrets_in_the_response_log(): void
    {
        // FAQ A10 step 2: GetTransactionDetails returns the voucher PIN and
        // expiry date in `details_list`.
        Http::fake([
            'https://api.basata.test/report' => Http::response([
                'success' => true,
                'data' => [
                    'transaction_details' => [
                        'provider_name' => 'Orange',
                        'details_list' => [
                            ['key' => 'voucher_pin', 'value' => '1234567890123456'],
                            ['key' => 'expiry_date', 'value' => '31/12/2030'],
                            ['key' => 'status_text', 'value' => 'Success'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $logged = $this->captureLogs(fn (ApiClient $client) => $client->getTransaction(123));

        $this->assertStringNotContainsString('1234567890123456', $logged);
        $this->assertStringNotContainsString('31/12/2030', $logged);
        // Ordinary fields still make it into the log — this is a mask, not a
        // blanket drop of the response body.
        $this->assertStringContainsString('Orange', $logged);
        $this->assertStringContainsString('Success', $logged);
    }

    public function test_masks_card_data_and_account_numbers_in_the_request_log(): void
    {
        Http::fake([
            'https://api.basata.test/transaction' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $logged = $this->captureLogs(fn (ApiClient $client) => $client->transactionInquiry([
            'service_version' => 3,
            'account_number' => '9876543210',
            'service_id' => 10,
            // PDF 5.9: `card_data` is a service input parameter.
            'input_parameter_list' => [
                ['key' => 'card_data', 'value' => '5555444433332222'],
                ['key' => 'customer_number', 'value' => '01000000000'],
            ],
        ]));

        $this->assertStringNotContainsString('9876543210', $logged);
        $this->assertStringNotContainsString('5555444433332222', $logged);
        $this->assertStringContainsString('TransactionInquiry', $logged);
        $this->assertStringContainsString('01000000000', $logged);
    }

    public function test_the_redact_list_is_configurable(): void
    {
        $this->app['config']->set('basata.logging.redact', ['info_text']);

        Http::fake([
            'https://api.basata.test/transaction' => Http::response([
                'success' => true,
                'data' => ['info_text' => 'due 250 EGP', 'account_number' => '9876543210'],
            ], 200),
        ]);

        $logged = $this->captureLogs(fn (ApiClient $client) => $client->transactionInquiry([
            'service_version' => 3,
            'account_number' => '9876543210',
            'service_id' => 10,
        ]));

        $this->assertStringNotContainsString('due 250 EGP', $logged);
        // Dropped from the list, so no longer masked.
        $this->assertStringContainsString('9876543210', $logged);
    }

    /** Runs $call with logging captured, and returns everything logged as JSON. */
    protected function captureLogs(callable $call): string
    {
        $contexts = [];

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$contexts) {
            $contexts[] = $context;
        });

        $call(new ApiClient());

        return json_encode($contexts);
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
