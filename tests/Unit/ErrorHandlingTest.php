<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\ApiClient;
use Ghanem\Bee\Exceptions\BeeInsufficientBalanceException;
use Ghanem\Bee\Exceptions\BeeServerException;
use Ghanem\Bee\Exceptions\BeeTransactionInProgressException;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ErrorHandlingTest extends TestCase
{
    public function test_a_200_response_with_success_false_is_not_treated_as_success(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'code' => 1016,
            'message' => 'Insufficient balance',
        ], 200)]);

        $this->expectException(BeeInsufficientBalanceException::class);

        app(ApiClient::class)->getAccountInfo();
    }

    public function test_the_thrown_exception_carries_the_api_code_and_payload(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'code' => 1034], 200)]);

        try {
            app(ApiClient::class)->getAccountInfo();
            $this->fail('Expected exception');
        } catch (BeeTransactionInProgressException $e) {
            $this->assertSame(1034, $e->apiCode);
            $this->assertArrayHasKey('code', $e->payload);
        }
    }

    public function test_an_undocumented_code_raises_the_server_exception(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'code' => 9999], 200)]);

        $this->expectException(BeeServerException::class);

        app(ApiClient::class)->getAccountInfo();
    }

    public function test_throwing_can_be_disabled(): void
    {
        config()->set('bee.errors.throw', false);
        Http::fake(['*' => Http::response(['success' => false, 'code' => 1016], 200)]);

        $result = app(ApiClient::class)->getAccountInfo();

        $this->assertSame(1016, $result['code']);
    }

    public function test_a_successful_response_still_returns_data(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['account_list' => []]], 200)]);

        $result = app(ApiClient::class)->getAccountInfo();

        $this->assertTrue($result['success']);
    }

    /**
     * Regression guard: extractApiCode() must never be consulted to decide
     * IF a response failed — only success itself decides that. status_code
     * is already overloaded elsewhere in this package (transport errors,
     * ApiResponse::toArray()), so its mere presence alongside success:true
     * must not be misread as an error code.
     */
    public function test_success_true_with_a_status_code_present_is_not_treated_as_failure(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'status_code' => 200,
            'data' => ['account_list' => []],
        ], 200)]);

        $result = app(ApiClient::class)->getAccountInfo();

        $this->assertTrue($result['success']);
    }

    public function test_a_200_response_with_an_empty_body_is_treated_as_failure(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->expectException(BeeServerException::class);

        app(ApiClient::class)->getAccountInfo();
    }

    public function test_a_200_response_with_a_non_json_body_is_treated_as_failure(): void
    {
        Http::fake(['*' => Http::response('<html>upstream WAF page</html>', 200)]);

        $this->expectException(BeeServerException::class);

        app(ApiClient::class)->getAccountInfo();
    }
}
