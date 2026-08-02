<?php

namespace Ghanem\Basata\Enums;

enum TransactionStatus: string
{
    case New = 'NEW';
    case InProgress = 'IN_PROGRESS';
    case Success = 'SUCCESS';
    case Error = 'ERROR';
    case DepositError = 'DEPOSIT_ERROR';

    // CANCELLED appears in the status object list (spec 4.9) but the FAQ Q9
    // finality table (page 20) only enumerates New/In Progress/Success/Error/
    // Deposit Error and is silent on it. Treated as final here: a cancelled
    // transaction will not progress further, so callers should stop polling.
    case Cancelled = 'CANCELLED';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Success, self::Error, self::DepositError, self::Cancelled => true,
            self::New, self::InProgress => false,
        };
    }
}
