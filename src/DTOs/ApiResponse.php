<?php

namespace Ghanem\Bee\DTOs;

class ApiResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly array $data,
        public readonly int $statusCode,
        public readonly ?string $error = null,
    ) {}

    public static function fromSuccess(array $json, int $statusCode = 200): static
    {
        return new static(
            success: true,
            data: $json['data'] ?? $json,
            statusCode: $statusCode,
        );
    }

    public static function fromError(array $json, int $statusCode): static
    {
        return new static(
            success: false,
            data: $json,
            statusCode: $statusCode,
            error: $json['message'] ?? $json['error'] ?? 'Unknown error',
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'status_code' => $this->statusCode,
            'error' => $this->error,
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }
}
