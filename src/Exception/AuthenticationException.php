<?php

declare(strict_types=1);

namespace Slash197\Reges\Exception;

class AuthenticationException extends RegesException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $error = null,
        public readonly ?string $errorDescription = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
