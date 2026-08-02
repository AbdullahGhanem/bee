<?php

namespace Ghanem\Basata\Jobs;

use Ghanem\Basata\BasataService;
use Ghanem\Basata\DTOs\TransactionResult;
use Ghanem\Basata\Events\TransactionStatusUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTransactionPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public int $backoff;

    public function __construct(
        public readonly array $data,
        public readonly ?string $lang = null,
    ) {
        $this->tries = config('basata.retry.tries', 3);
        $this->backoff = config('basata.retry.delay', 100);
        $this->onQueue(config('basata.queue.queue', 'default'));
        $this->onConnection(config('basata.queue.connection', config('queue.default')));
    }

    public function handle(BasataService $basata): void
    {
        $response = $basata->transactionPayment($this->data, $this->lang);

        if ($response instanceof \Illuminate\Support\Collection) {
            TransactionStatusUpdated::dispatch(
                $response->get('data')['transaction_id'] ?? 0,
                'completed',
                $response->toArray(),
            );
        }
    }
}
