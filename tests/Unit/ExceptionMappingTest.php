<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\Enums\TransactionStatus;
use Ghanem\Bee\Exceptions\BeeAuthenticationException;
use Ghanem\Bee\Exceptions\BeeException;
use Ghanem\Bee\Exceptions\BeeInsufficientBalanceException;
use Ghanem\Bee\Exceptions\BeeNotFoundException;
use Ghanem\Bee\Exceptions\BeeRateLimitException;
use Ghanem\Bee\Exceptions\BeeServerException;
use Ghanem\Bee\Exceptions\BeeTransactionInProgressException;
use Ghanem\Bee\Exceptions\BeeValidationException;
use Ghanem\Bee\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ExceptionMappingTest extends TestCase
{
    public function test_only_terminal_statuses_are_final(): void
    {
        $this->assertFalse(TransactionStatus::New->isFinal());
        $this->assertFalse(TransactionStatus::InProgress->isFinal());
        $this->assertTrue(TransactionStatus::Success->isFinal());
        $this->assertTrue(TransactionStatus::Error->isFinal());
        $this->assertTrue(TransactionStatus::DepositError->isFinal());
    }

    /**
     * @return iterable<string, array{int, class-string<BeeException>, string}>
     */
    public static function documentedCodeProvider(): iterable
    {
        yield 'auth' => [1003, BeeAuthenticationException::class, 'Incorrect login or password'];
        yield 'validation' => [1017, BeeValidationException::class, 'Wrong amount'];
        yield 'insufficient-balance' => [1016, BeeInsufficientBalanceException::class, 'Insufficient balance'];
        yield 'rate-limit' => [1033, BeeRateLimitException::class, 'Rate limit exceeded'];
        yield 'in-progress' => [1034, BeeTransactionInProgressException::class, 'Transaction is in progress, please try again later'];
        yield 'not-found' => [1026, BeeNotFoundException::class, 'Transaction not found'];
        yield 'server' => [2000, BeeServerException::class, 'Internal server error'];
    }

    #[DataProvider('documentedCodeProvider')]
    public function test_from_code_builds_the_right_exception_for_documented_codes(
        int $code,
        string $expectedClass,
        string $expectedMessage,
    ): void {
        $payload = ['raw' => 'response', 'code' => $code];

        $exception = BeeException::fromCode($code, $payload);

        $this->assertInstanceOf($expectedClass, $exception);
        $this->assertSame($code, $exception->apiCode);
        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($payload, $exception->payload);
        $this->assertSame($code, $exception->getCode());
    }

    public function test_from_code_falls_back_to_server_exception_for_undocumented_code(): void
    {
        $payload = ['raw' => 'response'];

        $exception = BeeException::fromCode(9999, $payload);

        $this->assertInstanceOf(BeeServerException::class, $exception);
        $this->assertSame(9999, $exception->apiCode);
        $this->assertSame('Unknown API error', $exception->getMessage());
        $this->assertSame($payload, $exception->payload);
    }

    public function test_from_code_falls_back_to_server_exception_for_null_code(): void
    {
        $payload = ['raw' => 'response'];

        $exception = BeeException::fromCode(null, $payload);

        $this->assertInstanceOf(BeeServerException::class, $exception);
        $this->assertNull($exception->apiCode);
        $this->assertSame('Unknown API error', $exception->getMessage());
        $this->assertSame($payload, $exception->payload);
        $this->assertSame(0, $exception->getCode());
    }
}
