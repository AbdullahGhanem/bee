<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\Enums\ErrorCode;
use Ghanem\Bee\Exceptions\BeeAuthenticationException;
use Ghanem\Bee\Exceptions\BeeInsufficientBalanceException;
use Ghanem\Bee\Exceptions\BeeRateLimitException;
use Ghanem\Bee\Tests\TestCase;

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
        $this->assertSame(BeeInsufficientBalanceException::class, ErrorCode::InsufficientBalance->exceptionClass());
        $this->assertSame(BeeRateLimitException::class, ErrorCode::RateLimitExceeded->exceptionClass());
        $this->assertSame(BeeAuthenticationException::class, ErrorCode::IncorrectCredentials->exceptionClass());
    }

    public function test_unknown_code_is_not_swallowed(): void
    {
        $this->assertNull(ErrorCode::tryFromCode(9999));
    }
}
