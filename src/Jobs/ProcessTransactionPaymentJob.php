<?php

namespace Ghanem\Bee\Jobs;

use Ghanem\Bee\BeeService;
use Ghanem\Bee\DTOs\TransactionResult;
use Ghanem\Bee\Events\TransactionStatusUpdated;
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
        public readonly string $lang = 'en',
    ) {
        $this->tries = config('bee.retry.tries', 3);
        $this->backoff = config('bee.retry.delay', 100);
        $this->onQueue(config('bee.queue.queue', 'default'));
        $this->onConnection(config('bee.queue.connection', config('queue.default')));
    }

    public function handle(BeeService $bee): void
    {
        $response = $bee->transactionPayment($this->data, $this->lang);

        if ($response instanceof \Illuminate\Support\Collection) {
            TransactionStatusUpdated::dispatch(
                $response->get('data')['transaction_id'] ?? 0,
                'completed',
                $response->toArray(),
            );
        }
    }
}
