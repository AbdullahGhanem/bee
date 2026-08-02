<?php

namespace Ghanem\Basata\Exceptions;

use Ghanem\Basata\Enums\ErrorCode;

class BasataException extends \RuntimeException
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
        $class = $enum?->exceptionClass() ?? BasataServerException::class;

        return new $class($code, $enum?->message() ?? 'Unknown API error', $payload);
    }
}
