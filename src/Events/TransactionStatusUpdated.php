<?php

namespace Ghanem\Bee\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int|string $transactionId,
        public readonly string $status,
        public readonly array $payload,
    ) {}
}
