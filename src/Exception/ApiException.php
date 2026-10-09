<?php

declare(strict_types=1);

namespace Slash197\Reges\Exception;

use Psr\Http\Message\ResponseInterface;

/**
 * A request that did not get the expected answer from REGES.
 *
 * isRetryable() tells a transient failure (no response at all, 5xx, 408, 429)
 * from a rejection that will fail the same way again (any other status).
 */
class ApiException extends RegesException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly string $body = '',
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    public static function fromResponse(string $action, ResponseInterface $response, ?string $requestId = null): self
    {
        $status = $response->getStatusCode();

        return new self(
            "REGES {$action} failed with HTTP {$status}.",
            $status,
            (string) $response->getBody(),
            $requestId,
        );
    }

    public function isRetryable(): bool
    {
        return $this->statusCode === null
            || $this->statusCode >= 500
            || in_array($this->statusCode, [408, 429], true);
    }
}
