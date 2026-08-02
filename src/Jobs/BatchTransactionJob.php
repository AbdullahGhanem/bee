<?php

namespace Ghanem\Basata\Jobs;

use Ghanem\Basata\BasataService;
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
        public readonly ?string $lang = null,
        public readonly ?string $callbackEvent = null,
    ) {
        $this->tries = config('basata.retry.tries', 3);
        $this->backoff = config('basata.retry.delay', 100);
        $this->onQueue(config('basata.queue.queue', 'default'));
        $this->onConnection(config('basata.queue.connection', config('queue.default')));
    }

    public function handle(BasataService $basata): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $result = match ($this->action) {
            'inquiry' => $basata->transactionInquiry($this->data, $this->lang),
            'payment' => $basata->transactionPayment($this->data, $this->lang),
            default => throw new \InvalidArgumentException("Unknown action: {$this->action}"),
        };

        if ($this->callbackEvent && class_exists($this->callbackEvent)) {
            event(new ($this->callbackEvent)($this->data, $result));
        }
    }
}
