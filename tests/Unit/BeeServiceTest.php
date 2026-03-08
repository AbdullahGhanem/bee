<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\BeeService;
use Ghanem\Bee\Facades\Bee;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class BeeServiceTest extends TestCase
{
    public function test_get_category_list_via_facade(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => ['categories' => []],
            ], 200),
        ]);

        $result = Bee::getCategoryList();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_get_category_service_list_via_facade(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $result = Bee::getCategoryServiceList();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_get_provider_list_via_facade(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => ['service_version' => 1],
            ], 200),
        ]);

        $result = Bee::getProviderList(3, 'ar');

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_get_service_list_via_facade(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $result = Bee::getServiceList();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_get_transaction_via_facade(): void
    {
        Http::fake([
            'https://api.bee.test/report' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 1],
            ], 200),
        ]);

        $result = Bee::getTransaction(1);

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_get_account_info_via_facade(): void
    {
        Http::fake([
            'https://api.bee.test/report' => Http::response([
                'success' => true,
                'data' => ['balance' => 500],
            ], 200),
        ]);

        $result = Bee::getAccountInfo();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_transaction_inquiry_fetches_service_version(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => ['service_version' => 5],
            ], 200),
            'https://api.bee.test/transaction' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 100],
            ], 200),
        ]);

        $result = Bee::transactionInquiry([
            'account_number' => '123',
            'service_id' => 10,
        ]);

        $this->assertInstanceOf(Collection::class, $result);

        // Should have made 2 requests: getProviderList + transactionInquiry
        Http::assertSentCount(2);
    }

    public function test_transaction_payment_fetches_service_version(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => ['service_version' => 5],
            ], 200),
            'https://api.bee.test/transaction' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 200],
            ], 200),
        ]);

        $result = Bee::transactionPayment([
            'account_number' => '123',
            'service_id' => 10,
            'amount' => 100,
        ]);

        $this->assertInstanceOf(Collection::class, $result);
        Http::assertSentCount(2);
    }

    public function test_calculate_service_charge_via_facade(): void
    {
        Http::fake([
            'https://api.bee.test/service' => Http::response([
                'success' => true,
                'data' => [
                    'service_list' => [
                        [
                            'id' => 10,
                            'service_charge_list' => [
                                ['from' => 0, 'to' => 1000, 'charge' => 5, 'percentage' => false, 'slap' => 0],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = Bee::calculateServiceCharge([
            'service_id' => 10,
            'amount' => 100,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(5, $result['service_charge']);
        $this->assertEquals(105, $result['total_amount']);
    }

    public function test_calculate_service_charge_reverse_via_facade(): void
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

        $result = Bee::calculateServiceChargeReverse([
            'service_id' => 10,
            'amount' => 110,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(100.0, $result['amount']);
    }

    public function test_get_bills_amount_via_facade(): void
    {
        Http::fake([
            'https://api.bee.test/transaction' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 50, 'amount' => 300],
            ], 200),
        ]);

        $result = Bee::getBillsAmount([
            'service_id' => 10,
            'account_number' => '123',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(50, $result['inquiry_transaction_id']);
        $this->assertEquals(300, $result['amount']);
    }

    public function test_bee_service_can_be_resolved_from_container(): void
    {
        $service = app(BeeService::class);

        $this->assertInstanceOf(BeeService::class, $service);
    }
}
