<?php

namespace Ghanem\Bee\Tests\Unit;

use Ghanem\Bee\Enums\TransactionStatus;
use Ghanem\Bee\Tests\TestCase;

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
}
