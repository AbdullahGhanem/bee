<?php

namespace Ghanem\Bee\DTOs;

class TransactionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $transactionId,
        public readonly ?float $amount,
        public readonly ?float $serviceCharge,
        public readonly ?float $totalAmount,
        public readonly array $raw,
        public readonly int $statusCode,
        public readonly ?string $error = null,
    ) {}

    public static function fromApiResponse(ApiResponse $response): static
    {
        return new static(
            success: $response->success,
            transactionId: $response->get('transaction_id'),
            amount: $response->get('amount') !== null ? (float) $response->get('amount') : null,
            serviceCharge: $response->get('service_charge') !== null ? (float) $response->get('service_charge') : null,
            totalAmount: $response->get('total_amount') !== null ? (float) $response->get('total_amount') : null,
            raw: $response->data,
            statusCode: $response->statusCode,
            error: $response->error,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'service_charge' => $this->serviceCharge,
            'total_amount' => $this->totalAmount,
            'error' => $this->error,
        ];
    }
}
