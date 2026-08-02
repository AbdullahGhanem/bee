<?php

namespace Ghanem\Basata\Tests\Unit;

use Ghanem\Basata\Enums\ErrorCode;
use Ghanem\Basata\Exceptions\BasataAuthenticationException;
use Ghanem\Basata\Exceptions\BasataInsufficientBalanceException;
use Ghanem\Basata\Exceptions\BasataRateLimitException;
use Ghanem\Basata\Tests\TestCase;

class ErrorCodeTest extends TestCase
{
    public function test_documented_codes_map_to_messages(): void
    {
        $this->assertSame('Incorrect login or password', ErrorCode::IncorrectCredentials->message());
        $this->assertSame('Insufficient balance', ErrorCode::InsufficientBalance->message());
        $this->assertSame('Rate limit exceeded', ErrorCode::RateLimitExceeded->message());
        $this->assertSame('Transaction is in progress, please try again later', ErrorCode::TransactionInProgress->message());
    }

    public function test_codes_map_to_exception_classes(): void
    {
        $this->assertSame(BasataInsufficientBalanceException::class, ErrorCode::InsufficientBalance->exceptionClass());
        $this->assertSame(BasataRateLimitException::class, ErrorCode::RateLimitExceeded->exceptionClass());
        $this->assertSame(BasataAuthenticationException::class, ErrorCode::IncorrectCredentials->exceptionClass());
    }

    public function test_unknown_code_is_not_swallowed(): void
    {
        $this->assertNull(ErrorCode::tryFromCode(9999));
    }
}
