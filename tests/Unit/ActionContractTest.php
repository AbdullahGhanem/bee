<?php

namespace Ghanem\Basata\Tests\Unit;

use Ghanem\Basata\ApiClient;
use Ghanem\Basata\Enums\OperationStatus;
use Ghanem\Basata\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The audit: one row per action in Channel API v3.0.8 (PDF sections 5.1-5.12,
 * request samples on pages 23-25). Each row pins down the path, action name,
 * version, and the exact `data` payload the PDF documents — nothing more,
 * nothing less.
 */
class ActionContractTest extends TestCase
{
    public static function actions(): array
    {
        return [
            // [method, args, expected path, expected action, expected data]
            'GetProviderList' => [
                'getProviderList', [], 'service', 'GetProviderList',
                ['service_version' => 0],
            ],
            'GetServiceList' => [
                'getServiceList', [], 'service', 'GetServiceList',
                [],
            ],
            'GetCategoryList' => [
                'getCategoryList', [], 'service', 'GetCategoryList',
                [],
            ],
            'GetCategoryServiceList' => [
                'getCategoryServiceList', [], 'service', 'GetCategoryServiceList',
                [],
            ],
            'GetServiceInputParameterList' => [
                'getServiceInputParameterList', [], 'service', 'GetServiceInputParameterList',
                [],
            ],
            'GetServiceOutputParameterList' => [
                'getServiceOutputParameterList', [], 'service', 'GetServiceOutputParameterList',
                [],
            ],
            'GetAccountInfo' => [
                'getAccountInfo', [], 'report', 'GetAccountInfo',
                [],
            ],
            'GetTransactionDetails' => [
                'getTransaction', [123, 'id'], 'report', 'GetTransactionDetails',
                ['transaction_id' => 123],
            ],
            'GetTransactionByExternalId' => [
                'getTransaction', ['ext-456', 'external_id'], 'report', 'GetTransactionByExternalId',
                ['external_id' => 'ext-456'],
            ],
            'TransactionInquiry' => [
                'transactionInquiry', [[
                    'service_version' => 3,
                    'account_number' => '12345',
                    'service_id' => 10,
                    'input_parameter_list' => [['key' => 'phone', 'value' => '123']],
                ]], 'transaction', 'TransactionInquiry',
                [
                    'service_version' => 3,
                    'account_number' => '12345',
                    'service_id' => 10,
                    'input_parameter_list' => [['key' => 'phone', 'value' => '123']],
                ],
            ],
            'TransactionPayment' => [
                'transactionPayment', [[
                    'external_id' => 'ext-1',
                    'service_version' => 3,
                    'account_number' => '12345',
                    'inquiry_transaction_id' => 50,
                    'service_id' => 10,
                    'amount' => 100,
                    'total_amount' => 105,
                    'quantity' => 1,
                    'input_parameter_list' => [],
                ]], 'transaction', 'TransactionPayment',
                [
                    'external_id' => 'ext-1',
                    'service_version' => 3,
                    'account_number' => '12345',
                    'inquiry_transaction_id' => 50,
                    'service_id' => 10,
                    'amount' => 100,
                    // Not passed in args -> ApiClient defaults it to 0. Real
                    // callers compute this via calculateServiceCharge(); see
                    // PDF FAQ A6 (p.19) / error 1022 for why it's sent at all
                    // despite being absent from §5.8's table.
                    'service_charge' => 0,
                    'total_amount' => 105,
                    'quantity' => 1,
                    'input_parameter_list' => [],
                ],
            ],
            'ConfirmPrepaidCardRecharge' => [
                'confirmPrepaidCardRecharge', ['225615364271', OperationStatus::Success], 'service', 'ConfirmPrepaidCardRecharge',
                ['payment_transaction_id' => '225615364271', 'operation_status' => 'SUCCESS'],
            ],
        ];
    }

    #[DataProvider('actions')]
    public function test_action_matches_the_v3_0_8_contract(string $method, array $args, string $path, string $action, array $expectedData): void
    {
        config()->set('basata.terminal_id', '1234567890');
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        app(ApiClient::class)->{$method}(...$args);

        Http::assertSent(function ($request) use ($path, $action, $expectedData) {
            // request()->data() returns the exact params array/object handed
            // to Http::post() (not a re-decoded wire body), and an empty
            // `data` is deliberately sent as an object — (object) [] encodes
            // to the PDF's "data": {} — so normalize to an array here and
            // compare key/value pairs regardless of order.
            $actualData = (array) $request['data'];
            ksort($actualData);
            $expected = $expectedData;
            ksort($expected);

            // (array) $request['data'] would pass even if the code regressed
            // from an empty object back to an empty array (both cast to
            // []), which is exactly the bug being pinned down — so for the
            // empty-data actions, also check the literal wire bytes contain
            // the PDF's "data":{} rather than "data":[].
            $wireBytesOk = $expectedData !== []
                || str_contains($request->body(), '"data":{}');

            return str_ends_with($request->url(), '/'.$path)
                && $request['action'] === $action
                && $request['version'] === 2
                && $request['language'] === 'en'
                && $request['terminal_id'] === '1234567890'
                && array_key_exists('login', $request->data())
                && array_key_exists('password', $request->data())
                && $actualData === $expected
                && $wireBytesOk;
        });
    }
}
