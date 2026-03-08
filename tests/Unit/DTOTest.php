<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\DTOs\ApiResponse;
use Ghanem\Bee\DTOs\ServiceChargeResult;
use Ghanem\Bee\DTOs\TransactionResult;
use Ghanem\Bee\Tests\TestCase;

class DTOTest extends TestCase
{
    public function test_api_response_from_success(): void
    {
        $response = ApiResponse::fromSuccess(['data' => ['id' => 1]], 200);

        $this->assertTrue($response->success);
        $this->assertEquals(['id' => 1], $response->data);
        $this->assertEquals(200, $response->statusCode);
        $this->assertNull($response->error);
    }

    public function test_api_response_from_error(): void
    {
        $response = ApiResponse::fromError(['message' => 'Not found'], 404);

        $this->assertFalse($response->success);
        $this->assertEquals(404, $response->statusCode);
        $this->assertEquals('Not found', $response->error);
    }

    public function test_api_response_to_array(): void
    {
        $response = ApiResponse::fromSuccess(['data' => ['id' => 1]]);

        $array = $response->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('status_code', $array);
        $this->assertArrayHasKey('error', $array);
    }

    public function test_api_response_get_helper(): void
    {
        $response = ApiResponse::fromSuccess(['data' => ['nested' => ['value' => 42]]]);

        $this->assertEquals(42, $response->get('nested.value'));
        $this->assertEquals('default', $response->get('missing', 'default'));
    }

    public function test_api_response_from_error_with_error_key(): void
    {
        $response = ApiResponse::fromError(['error' => 'Unauthorized'], 401);

        $this->assertEquals('Unauthorized', $response->error);
    }

    public function test_api_response_from_error_with_unknown_format(): void
    {
        $response = ApiResponse::fromError(['code' => 500], 500);

        $this->assertEquals('Unknown error', $response->error);
    }

    public function test_transaction_result_from_api_response(): void
    {
        $apiResponse = ApiResponse::fromSuccess([
            'data' => [
                'transaction_id' => 123,
                'amount' => 100.5,
                'service_charge' => 5.0,
                'total_amount' => 105.5,
            ],
        ]);

        $result = TransactionResult::fromApiResponse($apiResponse);

        $this->assertTrue($result->success);
        $this->assertEquals(123, $result->transactionId);
        $this->assertEquals(100.5, $result->amount);
        $this->assertEquals(5.0, $result->serviceCharge);
        $this->assertEquals(105.5, $result->totalAmount);
    }

    public function test_transaction_result_from_error_response(): void
    {
        $apiResponse = ApiResponse::fromError(['message' => 'Failed'], 500);

        $result = TransactionResult::fromApiResponse($apiResponse);

        $this->assertFalse($result->success);
        $this->assertNull($result->transactionId);
        $this->assertEquals('Failed', $result->error);
    }

    public function test_transaction_result_to_array(): void
    {
        $apiResponse = ApiResponse::fromSuccess([
            'data' => ['transaction_id' => 1, 'amount' => 50],
        ]);

        $result = TransactionResult::fromApiResponse($apiResponse);
        $array = $result->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('transaction_id', $array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertArrayHasKey('service_charge', $array);
        $this->assertArrayHasKey('total_amount', $array);
    }

    public function test_service_charge_result_from_array(): void
    {
        $result = ServiceChargeResult::fromArray([
            'service_id' => 10,
            'amount' => 100,
            'service_charge' => 5,
            'total_amount' => 105,
        ]);

        $this->assertEquals(10, $result->serviceId);
        $this->assertEquals(100.0, $result->amount);
        $this->assertEquals(5.0, $result->serviceCharge);
        $this->assertEquals(105.0, $result->totalAmount);
    }

    public function test_service_charge_result_to_array(): void
    {
        $result = ServiceChargeResult::fromArray([
            'service_id' => 10,
            'amount' => 100,
            'service_charge' => 5,
            'total_amount' => 105,
        ]);

        $array = $result->toArray();

        $this->assertEquals([
            'service_id' => 10,
            'amount' => 100.0,
            'service_charge' => 5.0,
            'total_amount' => 105.0,
        ], $array);
    }
}
