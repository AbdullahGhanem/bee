<?php

namespace Ghanem\Bee\Exceptions;

use Ghanem\Bee\Enums\ErrorCode;

class BeeException extends \RuntimeException
{
    public function __construct(
        public readonly ?int $apiCode,
        string $message,
        public readonly array $payload = [],
    ) {
        parent::__construct($message, $apiCode ?? 0);
    }

    public static function fromCode(?int $code, array $payload = []): static
    {
        $enum = $code === null ? null : ErrorCode::tryFromCode($code);
        $class = $enum?->exceptionClass() ?? BeeServerException::class;

        return new $class($code, $enum?->message() ?? 'Unknown API error', $payload);
    }
}
