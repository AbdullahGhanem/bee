<?php

namespace Ghanem\Bee\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BeeWebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $event,
        public readonly array $payload,
    ) {}
}
