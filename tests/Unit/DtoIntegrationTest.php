<?php

namespace Ghanem\Basata\Tests\Unit;

use Ghanem\Basata\DTOs\ApiResponse;
use Ghanem\Basata\DTOs\ServiceChargeResult;
use Ghanem\Basata\DTOs\TransactionResult;
use Ghanem\Basata\Facades\Basata;
use Ghanem\Basata\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class DtoIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('basata.cache.enabled', false);
    }

    public function test_get_category_list_dto(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response([
                'success' => true,
                'data' => ['categories' => [['id' => 1]]],
            ], 200),
        ]);

        $result = Basata::getCategoryListDto();

        $this->assertInstanceOf(ApiResponse::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(200, $result->statusCode);
    }

    public function test_get_service_list_dto(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response([
                'success' => true,
                'data' => ['service_list' => []],
            ], 200),
        ]);

        $result = Basata::getServiceListDto();

        $this->assertInstanceOf(ApiResponse::class, $result);
        $this->assertTrue($result->success);
    }

    public function test_get_transaction_dto(): void
    {
        Http::fake([
            'https://api.basata.test/report' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 123, 'amount' => 50],
            ], 200),
        ]);

        $result = Basata::getTransactionDto(123);

        $this->assertInstanceOf(TransactionResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(123, $result->transactionId);
        $this->assertEquals(50.0, $result->amount);
    }

    public function test_get_transaction_dto_with_error(): void
    {
        Http::fake([
            'https://api.basata.test/report' => Http::response([
                'message' => 'Transaction not found',
            ], 404),
        ]);

        $result = Basata::getTransactionDto(999);

        $this->assertInstanceOf(TransactionResult::class, $result);
        $this->assertFalse($result->success);
        $this->assertEquals('Transaction not found', $result->error);
    }

    public function test_transaction_inquiry_dto(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response([
                'success' => true,
                'data' => ['service_version' => 3],
            ], 200),
            'https://api.basata.test/transaction' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 100, 'amount' => 50],
            ], 200),
        ]);

        $result = Basata::transactionInquiryDto([
            'account_number' => '123',
            'service_id' => 10,
        ]);

        $this->assertInstanceOf(TransactionResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(100, $result->transactionId);
    }

    public function test_transaction_payment_dto(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response([
                'success' => true,
                'data' => ['service_version' => 3],
            ], 200),
            'https://api.basata.test/transaction' => Http::response([
                'success' => true,
                'data' => [
                    'transaction_id' => 200,
                    'amount' => 100,
                    'service_charge' => 5,
                    'total_amount' => 105,
                ],
            ], 200),
        ]);

        $result = Basata::transactionPaymentDto([
            'account_number' => '123',
            'service_id' => 10,
            'external_id' => 'ext-1',
            'amount' => 100,
            'total_amount' => 105,
            'quantity' => 1,
        ]);

        $this->assertInstanceOf(TransactionResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(200, $result->transactionId);
        $this->assertEquals(100.0, $result->amount);
        $this->assertEquals(5.0, $result->serviceCharge);
        $this->assertEquals(105.0, $result->totalAmount);
    }

    public function test_calculate_service_charge_dto(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response([
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

        $result = Basata::calculateServiceChargeDto([
            'service_id' => 10,
            'amount' => 100,
        ]);

        $this->assertInstanceOf(ServiceChargeResult::class, $result);
        $this->assertEquals(10, $result->serviceId);
        $this->assertEquals(100.0, $result->amount);
        $this->assertEquals(5.0, $result->serviceCharge);
        $this->assertEquals(105.0, $result->totalAmount);
    }

    public function test_calculate_service_charge_reverse_dto(): void
    {
        Http::fake([
            'https://api.basata.test/service' => Http::response([
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

        $result = Basata::calculateServiceChargeReverseDto([
            'service_id' => 10,
            'amount' => 110,
        ]);

        $this->assertInstanceOf(ServiceChargeResult::class, $result);
        $this->assertEquals(100.0, $result->amount);
    }
}
