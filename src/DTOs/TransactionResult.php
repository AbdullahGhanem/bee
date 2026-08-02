<?php

namespace Ghanem\Basata\DTOs;

class TransactionResult
{
    public function __construct(
        public readonly bool $success,
        // The spec types transaction_id as a String everywhere (4.9, 4.10,
        // 5.7, 5.8, 5.11; FAQ Q17's sample is "225615364271"). Coercing to int
        // dropped leading zeros and TypeError'd on a non-numeric ID, so the
        // value is passed through exactly as the API sent it.
        public readonly int|string|null $transactionId,
        public readonly ?float $amount,
        public readonly ?float $serviceCharge,
        public readonly ?float $totalAmount,
        public readonly array $raw,
        public readonly int $statusCode,
        public readonly ?string $error = null,
    ) {}

    /**
     * Two wire shapes feed this DTO and both have to work:
     *
     *  - GetTransactionDetails / GetTransactionByExternalId (5.11, 5.12) nest
     *    the record under `data.transaction_details` as a Transaction Detail
     *    object (4.10: provider_name, service_name, customer_number, amount,
     *    total_amount, balance, date_time, status, status_text, error_text,
     *    details_list).
     *  - TransactionInquiry / TransactionPayment (5.7, 5.8) return their
     *    fields FLAT in `data`.
     *
     * So each field is read from the nested object first and falls back to the
     * flat level. 4.10 lists neither transaction_id nor service_charge, so for
     * the report actions those stay null unless the API echoes them at the
     * flat level — the fallback picks them up if it does.
     */
    public static function fromApiResponse(ApiResponse $response): static
    {
        $get = fn (string $key) => $response->get("transaction_details.$key") ?? $response->get($key);
        $float = fn (string $key) => ($value = $get($key)) !== null ? (float) $value : null;

        return new static(
            success: $response->success,
            transactionId: $get('transaction_id'),
            amount: $float('amount'),
            serviceCharge: $float('service_charge'),
            totalAmount: $float('total_amount'),
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
