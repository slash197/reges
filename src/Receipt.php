<?php

declare(strict_types=1);

namespace Slash197\Reges;

/**
 * Proof that REGES queued a message. It says nothing about the outcome: that
 * arrives later as a {@see Results\Result} carrying the same message id.
 */
final readonly class Receipt
{
    /**
     * @param string|null  $messageId  The id the message was sent with
     * @param string|null  $responseId The id REGES assigned to the submission ("recipisa")
     * @param array<mixed> $raw        The decoded response body
     */
    public function __construct(
        public ?string $messageId,
        public ?string $responseId,
        public int $statusCode,
        public array $raw = [],
    ) {
    }
}
