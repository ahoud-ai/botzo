<?php

namespace App\Modules\Platform\Domain\Exceptions;

use RuntimeException;

class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
