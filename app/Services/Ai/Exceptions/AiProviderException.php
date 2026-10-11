<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * The message keeps the provider's own error code/text (e.g. "model_not_found")
 * so callers can tell configuration problems from outages.
 */
class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
