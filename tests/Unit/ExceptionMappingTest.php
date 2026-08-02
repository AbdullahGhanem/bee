<?php

namespace Ghanem\Basata\Tests\Unit;

use Ghanem\Basata\Enums\TransactionStatus;
use Ghanem\Basata\Exceptions\BasataAuthenticationException;
use Ghanem\Basata\Exceptions\BasataDuplicateTransactionIdException;
use Ghanem\Basata\Exceptions\BasataException;
use Ghanem\Basata\Exceptions\BasataInsufficientBalanceException;
use Ghanem\Basata\Exceptions\BasataNotFoundException;
use Ghanem\Basata\Exceptions\BasataRateLimitException;
use Ghanem\Basata\Exceptions\BasataServerException;
use Ghanem\Basata\Exceptions\BasataTransactionInProgressException;
use Ghanem\Basata\Exceptions\BasataValidationException;
use Ghanem\Basata\Tests\TestCase;
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
     * @return iterable<string, array{int, class-string<BasataException>, string}>
     */
    public static function documentedCodeProvider(): iterable
    {
        yield 'auth' => [1003, BasataAuthenticationException::class, 'Incorrect login or password'];
        yield 'validation' => [1017, BasataValidationException::class, 'Wrong amount'];
        // 1023 is separately catchable (FAQ A10: it can mean the payment
        // actually succeeded) but must still satisfy a catch on the base
        // validation class — the provider asserts instanceof, not identity.
        yield 'duplicate-external-id' => [1023, BasataDuplicateTransactionIdException::class, 'Duplicate transaction ID'];
        yield 'duplicate-external-id-is-still-a-validation-error' => [1023, BasataValidationException::class, 'Duplicate transaction ID'];
        yield 'insufficient-balance' => [1016, BasataInsufficientBalanceException::class, 'Insufficient balance'];
        yield 'rate-limit' => [1033, BasataRateLimitException::class, 'Rate limit exceeded'];
        yield 'in-progress' => [1034, BasataTransactionInProgressException::class, 'Transaction is in progress, please try again later'];
        yield 'not-found' => [1026, BasataNotFoundException::class, 'Transaction not found'];
        yield 'server' => [2000, BasataServerException::class, 'Internal server error'];
    }

    #[DataProvider('documentedCodeProvider')]
    public function test_from_code_builds_the_right_exception_for_documented_codes(
        int $code,
        string $expectedClass,
        string $expectedMessage,
    ): void {
        $payload = ['raw' => 'response', 'code' => $code];

        $exception = BasataException::fromCode($code, $payload);

        $this->assertInstanceOf($expectedClass, $exception);
        $this->assertSame($code, $exception->apiCode);
        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($payload, $exception->payload);
        $this->assertSame($code, $exception->getCode());
    }

    public function test_from_code_falls_back_to_server_exception_for_undocumented_code(): void
    {
        $payload = ['raw' => 'response'];

        $exception = BasataException::fromCode(9999, $payload);

        $this->assertInstanceOf(BasataServerException::class, $exception);
        $this->assertSame(9999, $exception->apiCode);
        $this->assertSame('Unknown API error', $exception->getMessage());
        $this->assertSame($payload, $exception->payload);
    }

    public function test_from_code_falls_back_to_server_exception_for_null_code(): void
    {
        $payload = ['raw' => 'response'];

        $exception = BasataException::fromCode(null, $payload);

        $this->assertInstanceOf(BasataServerException::class, $exception);
        $this->assertNull($exception->apiCode);
        $this->assertSame('Unknown API error', $exception->getMessage());
        $this->assertSame($payload, $exception->payload);
        $this->assertSame(0, $exception->getCode());
    }
}
