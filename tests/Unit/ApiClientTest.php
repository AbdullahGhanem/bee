<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\ApiClient;
use Ghanem\Bee\Exceptions\BeeValidationException;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ApiClientTest extends TestCase
{
    protected ApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new ApiClient();
    }

    public function test_request_returns_collection_on_success(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $result = $this->client->request('service', ['action' => 'Test']);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result['success']);
    }

    public function test_request_returns_error_array_on_failure(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = $this->client->request('service', ['action' => 'Test']);

        $this->assertIsArray($result);
        $this->assertEquals(401, $result['status_code']);
        $this->assertEquals('Unauthorized', $result['error']);
    }

    public function test_request_includes_credentials(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true], 200),
        ]);

        $this->client->request('service', ['action' => 'Test']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['login'] === 'test-user'
                && $body['password'] === 'test-pass'
                && $body['action'] === 'Test';
        });
    }

    public function test_get_category_list(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => ['categories' => [['id' => 1, 'name' => 'Telecom']]],
            ], 200),
        ]);

        $result = $this->client->getCategoryList();

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSent(fn ($r) => $r->data()['action'] === 'GetCategoryList' && $r->data()['language'] === 'en');
    }

    public function test_get_category_list_with_language(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $this->client->getCategoryList('ar');

        Http::assertSent(fn ($r) => $r->data()['language'] === 'ar');
    }

    public function test_get_category_service_list(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $result = $this->client->getCategoryServiceList();

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSent(fn ($r) => $r->data()['action'] === 'GetCategoryServiceList');
    }

    public function test_get_provider_list(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => ['service_version' => 3, 'providers' => []],
            ], 200),
        ]);

        $result = $this->client->getProviderList(5, 'en');

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSent(fn ($r) => $r->data()['action'] === 'GetProviderList');
    }

    public function test_get_service_list(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => ['service_list' => [['id' => 1, 'name' => 'Service 1']]],
            ], 200),
        ]);

        $result = $this->client->getServiceList();

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSent(fn ($r) => $r->data()['action'] === 'GetServiceList');
    }

    public function test_get_service_input_parameter_list(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $result = $this->client->getServiceInputParameterList();

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSent(fn ($r) => $r->data()['action'] === 'GetServiceInputParameterList');
    }

    public function test_get_service_output_parameter_list(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $result = $this->client->getServiceOutputParameterList();

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSent(fn ($r) => $r->data()['action'] === 'GetServiceOutputParameterList');
    }

    public function test_get_transaction_by_id(): void
    {
        Http::fake([
            'https://api.bee.test/report' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 123],
            ], 200),
        ]);

        $result = $this->client->getTransaction(123);

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSent(function ($r) {
            return $r->data()['action'] === 'GetTransactionDetails'
                && $r->data()['data']['transaction_id'] === 123;
        });
    }

    public function test_get_transaction_by_external_id(): void
    {
        Http::fake([
            'https://api.bee.test/report' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 123],
            ], 200),
        ]);

        $result = $this->client->getTransaction('ext-456', 'external_id');

        Http::assertSent(function ($r) {
            return $r->data()['action'] === 'GetTransactionByExternalId'
                && $r->data()['data']['external_id'] === 'ext-456';
        });
    }

    public function test_get_account_info(): void
    {
        Http::fake([
            'https://api.bee.test/report' => Http::response([
                'success' => true,
                'data' => ['balance' => 1000],
            ], 200),
        ]);

        $result = $this->client->getAccountInfo();

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSent(fn ($r) => $r->data()['action'] === 'GetAccountInfo');
    }

    public function test_transaction_inquiry(): void
    {
        Http::fake([
            'https://api.bee.test/transaction' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 100, 'amount' => 50],
            ], 200),
        ]);

        $result = $this->client->transactionInquiry([
            'service_version' => 3,
            'account_number' => '12345',
            'service_id' => 10,
            'input_parameter_list' => [['key' => 'phone', 'value' => '123']],
        ]);

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSent(function ($r) {
            $data = $r->data();

            return $data['action'] === 'TransactionInquiry'
                && $data['data']['service_id'] === 10
                && $data['data']['account_number'] === '12345';
        });
    }

    public function test_transaction_inquiry_throws_instead_of_defaulting_missing_required_fields(): void
    {
        // PDF 5.7 (p.13): service_version, account_number and service_id are
        // all required (+). Silently defaulting them (the old behavior) can
        // route a real inquiry against the wrong service. See
        // RequiredFieldsTest for per-field coverage of the exact codes.
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $this->expectException(BeeValidationException::class);

        $this->client->transactionInquiry([]);
    }

    public function test_transaction_payment(): void
    {
        Http::fake([
            'https://api.bee.test/transaction' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 200],
            ], 200),
        ]);

        $result = $this->client->transactionPayment([
            'service_version' => 3,
            'account_number' => '12345',
            'service_id' => 10,
            'external_id' => 'ext-1',
            'amount' => 100,
            'service_charge' => 5,
            'total_amount' => 105,
            'quantity' => 1,
            'inquiry_transaction_id' => 50,
            'input_parameter_list' => [],
        ]);

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSent(function ($r) {
            $data = $r->data();

            return $data['action'] === 'TransactionPayment'
                && $data['data']['amount'] === 100
                && $data['data']['service_charge'] === 5
                && $data['data']['total_amount'] === 105;
        });
    }

    public function test_transaction_payment_sends_zero_service_charge_when_not_supplied(): void
    {
        // service_charge is real (PDF FAQ A6 p.19, error 1022 p.18) but
        // genuinely optional client-side (calculateServiceCharge() computes
        // it) — 0 is the sane default, unlike the required fields below.
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $this->client->transactionPayment([
            'service_version' => 3,
            'account_number' => '12345',
            'service_id' => 10,
            'external_id' => 'ext-1',
            'amount' => 100,
            'total_amount' => 100,
            'quantity' => 1,
        ]);

        Http::assertSent(fn ($r) => $r->data()['data']['service_charge'] === 0);
    }

    public function test_transaction_payment_throws_instead_of_defaulting_missing_required_fields(): void
    {
        // PDF 5.8 (p.14): external_id, service_version, account_number,
        // service_id, amount, total_amount and quantity are all required
        // (+). Silently defaulting them (the old behavior — amount => 1.5,
        // service_id => 14, etc.) could post a real payment with the wrong
        // amount against the wrong service. See RequiredFieldsTest for
        // per-field coverage of the exact codes.
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $this->expectException(BeeValidationException::class);

        $this->client->transactionPayment([]);
    }

    public function test_calculate_service_charge_with_percentage(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => [
                    'service_list' => [
                        [
                            'id' => 10,
                            'service_charge_list' => [
                                ['from' => 0, 'to' => 1000, 'charge' => 10, 'percentage' => true, 'slap' => 5],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->calculateServiceCharge([
            'service_id' => 10,
            'amount' => 100,
        ]);

        $this->assertEquals(10.0, $result['service_charge']); // 10% of 100
        $this->assertEquals(110.0, $result['total_amount']);
    }

    public function test_calculate_service_charge_with_flat_rate(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => [
                    'service_list' => [
                        [
                            'id' => 10,
                            'service_charge_list' => [
                                ['from' => 0, 'to' => 1000, 'charge' => 15, 'percentage' => false, 'slap' => 0],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->calculateServiceCharge([
            'service_id' => 10,
            'amount' => 100,
        ]);

        $this->assertEquals(15, $result['service_charge']);
        $this->assertEquals(115, $result['total_amount']);
    }

    public function test_calculate_service_charge_respects_minimum_slap(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => [
                    'service_list' => [
                        [
                            'id' => 10,
                            'service_charge_list' => [
                                ['from' => 0, 'to' => 1000, 'charge' => 1, 'percentage' => true, 'slap' => 5],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->calculateServiceCharge([
            'service_id' => 10,
            'amount' => 100,
        ]);

        // 1% of 100 = 1, but slap minimum is 5
        $this->assertEquals(5, $result['service_charge']);
        $this->assertEquals(105, $result['total_amount']);
    }

    public function test_calculate_service_charge_reverse(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => [
                    'service_list' => [
                        [
                            'id' => 10,
                            'service_charge_list' => [
                                ['from' => 0, 'to' => 1000, 'charge' => 10, 'percentage' => true, 'slap' => 0],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client->calculateServiceChargeReverse([
            'service_id' => 10,
            'amount' => 110, // total amount including charge
        ]);

        $this->assertEquals(100.0, $result['amount']);
        $this->assertEqualsWithDelta(10.0, $result['service_charge'], 0.01);
        $this->assertEqualsWithDelta(110.0, $result['total_amount'], 0.01);
    }

    public function test_get_bills_amount(): void
    {
        Http::fake([
            'https://api.bee.test/transaction' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 100, 'amount' => 250],
            ], 200),
        ]);

        $result = $this->client->getBillsAmount([
            'service_version' => 3,
            'service_id' => 10,
            'account_number' => '12345',
        ]);

        $this->assertEquals(100, $result['inquiry_transaction_id']);
        $this->assertEquals(250, $result['amount']);
    }

    public function test_request_sends_to_correct_url(): void
    {
        Http::fake([
            'https://api.bee.test/report' => Http::response(['success' => true], 200),
        ]);

        $this->client->getAccountInfo();

        Http::assertSent(fn ($r) => $r->url() === 'https://api.bee.test/report');
    }

    public function test_all_service_endpoints_use_service_url(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $methods = [
            'getCategoryList',
            'getCategoryServiceList',
            'getServiceList',
            'getServiceInputParameterList',
            'getServiceOutputParameterList',
        ];

        foreach ($methods as $method) {
            $this->client->$method();
        }

        Http::assertSentCount(5);
    }
}
