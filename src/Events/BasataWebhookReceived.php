<?php

namespace Ghanem\Basata\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BasataWebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $event,
        public readonly array $payload,
    ) {}
}
