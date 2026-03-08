<?php

namespace Ghanem\Bee\Jobs;

use Ghanem\Bee\BeeService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BatchTransactionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public int $backoff;

    public function __construct(
        public readonly string $action,
        public readonly array $data,
        public readonly string $lang = 'en',
        public readonly ?string $callbackEvent = null,
    ) {
        $this->tries = config('bee.retry.tries', 3);
        $this->backoff = config('bee.retry.delay', 100);
        $this->onQueue(config('bee.queue.queue', 'default'));
        $this->onConnection(config('bee.queue.connection', config('queue.default')));
    }

    public function handle(BeeService $bee): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $result = match ($this->action) {
            'inquiry' => $bee->transactionInquiry($this->data, $this->lang),
            'payment' => $bee->transactionPayment($this->data, $this->lang),
            default => throw new \InvalidArgumentException("Unknown action: {$this->action}"),
        };

        if ($this->callbackEvent && class_exists($this->callbackEvent)) {
            event(new ($this->callbackEvent)($this->data, $result));
        }
    }
}
