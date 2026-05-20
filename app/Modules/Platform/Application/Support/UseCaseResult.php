<?php

namespace App\Modules\Platform\Application\Support;

class UseCaseResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $data = [],
        public readonly array $errors = [],
        public readonly ?string $message = null,
        public readonly int $status = 200,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data = [], ?string $message = null, int $status = 200): self
    {
        return new self(true, $data, [], $message, $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function failure(array $errors = [], ?string $message = null, int $status = 422): self
    {
        return new self(false, [], $errors, $message, $status);
    }
}
