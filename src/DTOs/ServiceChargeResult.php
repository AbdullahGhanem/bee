<?php

namespace Ghanem\Bee\DTOs;

class ServiceChargeResult
{
    public function __construct(
        public readonly int $serviceId,
        public readonly float $amount,
        public readonly float $serviceCharge,
        public readonly float $totalAmount,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            serviceId: $data['service_id'],
            amount: (float) $data['amount'],
            serviceCharge: (float) $data['service_charge'],
            totalAmount: (float) $data['total_amount'],
        );
    }

    public function toArray(): array
    {
        return [
            'service_id' => $this->serviceId,
            'amount' => $this->amount,
            'service_charge' => $this->serviceCharge,
            'total_amount' => $this->totalAmount,
        ];
    }
}
