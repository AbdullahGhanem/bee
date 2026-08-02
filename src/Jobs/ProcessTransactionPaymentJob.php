<?php

namespace Ghanem\Basata\Jobs;

use Ghanem\Basata\BasataService;
use Ghanem\Basata\Enums\TransactionStatus;
use Ghanem\Basata\Events\TransactionStatusUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

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

    /**
     * `success: true` only means the request was accepted — per spec 5.8 and
     * FAQ Q9 the payment itself can come back IN_PROGRESS, ERROR or
     * DEPOSIT_ERROR, so the event carries the response's real status rather
     * than a hardcoded "completed". A response with no status at all is
     * reported as IN_PROGRESS: the non-final value, which tells listeners to
     * keep polling instead of booking a payment that never succeeded.
     */
    public function handle(BasataService $basata): void
    {
        $response = $basata->transactionPayment($this->data, $this->lang);

        if (! $response instanceof Collection) {
            return;
        }

        $data = $response->get('data');
        $data = is_array($data) ? $data : [];

        $status = TransactionStatus::tryFrom((string) ($data['status'] ?? ''))
            ?? TransactionStatus::InProgress;

        TransactionStatusUpdated::dispatch(
            $data['transaction_id'] ?? '',
            $status->value,
            $response->toArray(),
        );
    }
}
