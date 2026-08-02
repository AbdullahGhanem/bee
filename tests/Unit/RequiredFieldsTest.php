<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\ApiClient;
use Ghanem\Bee\Enums\ErrorCode;
use Ghanem\Bee\Exceptions\BeeValidationException;
use Ghanem\Bee\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * TransactionInquiry (PDF 5.7, p.13) and TransactionPayment (PDF 5.8, p.14)
 * used to silently default every required (+) field a caller forgot —
 * amount => 1.5, service_id => 14, account_number => 2, etc. A forgotten
 * `amount` would silently post a real 1.5 EGP payment against service 14
 * instead of failing loudly. Each required field must now throw
 * BeeValidationException with the documented code instead.
 */
class RequiredFieldsTest extends TestCase
{
    public static function requiredFields(): array
    {
        $inquiryBase = [
            'service_version' => 3,
            'account_number' => '12345',
            'service_id' => 10,
        ];

        $paymentBase = [
            'service_version' => 3,
            'account_number' => '12345',
            'service_id' => 10,
            'external_id' => 'ext-1',
            'amount' => 100,
            'total_amount' => 100,
            'quantity' => 1,
        ];

        return [
            // [method, base data, field to omit, expected ErrorCode]
            'TransactionInquiry: service_version' => ['transactionInquiry', $inquiryBase, 'service_version', ErrorCode::DataRequired],
            'TransactionInquiry: account_number' => ['transactionInquiry', $inquiryBase, 'account_number', ErrorCode::DataRequired],
            'TransactionInquiry: service_id' => ['transactionInquiry', $inquiryBase, 'service_id', ErrorCode::DataRequired],

            'TransactionPayment: service_version' => ['transactionPayment', $paymentBase, 'service_version', ErrorCode::DataRequired],
            'TransactionPayment: account_number' => ['transactionPayment', $paymentBase, 'account_number', ErrorCode::DataRequired],
            'TransactionPayment: service_id' => ['transactionPayment', $paymentBase, 'service_id', ErrorCode::DataRequired],
            'TransactionPayment: external_id' => ['transactionPayment', $paymentBase, 'external_id', ErrorCode::DataRequired],
            'TransactionPayment: quantity' => ['transactionPayment', $paymentBase, 'quantity', ErrorCode::DataRequired],
            'TransactionPayment: amount' => ['transactionPayment', $paymentBase, 'amount', ErrorCode::WrongAmount],
            'TransactionPayment: total_amount' => ['transactionPayment', $paymentBase, 'total_amount', ErrorCode::WrongAmount],
        ];
    }

    #[DataProvider('requiredFields')]
    public function test_missing_required_field_throws_the_documented_code(string $method, array $baseData, string $omittedField, ErrorCode $expectedCode): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $data = $baseData;
        unset($data[$omittedField]);

        try {
            app(ApiClient::class)->{$method}($data);
            $this->fail("Expected BeeValidationException for missing `{$omittedField}`");
        } catch (BeeValidationException $e) {
            $this->assertSame($expectedCode->value, $e->apiCode);
        }

        Http::assertNothingSent();
    }

    public function test_present_but_empty_string_is_also_treated_as_missing(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $this->expectException(BeeValidationException::class);

        app(ApiClient::class)->transactionPayment([
            'service_version' => 3,
            'account_number' => '',
            'service_id' => 10,
            'external_id' => 'ext-1',
            'amount' => 100,
            'total_amount' => 100,
            'quantity' => 1,
        ]);
    }

    public function test_zero_is_a_legitimate_value_not_a_missing_one(): void
    {
        // service_version 0 is documented as legitimate ("first time use 0",
        // PDF 5.1 p.10) — requireField() must not treat 0 as absent the way
        // it treats null/''.
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        app(ApiClient::class)->transactionInquiry([
            'service_version' => 0,
            'account_number' => '12345',
            'service_id' => 10,
        ]);

        Http::assertSent(fn ($r) => $r->data()['data']['service_version'] === 0);
    }
}
