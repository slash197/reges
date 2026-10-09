<?php

declare(strict_types=1);

namespace slash197\Reges;

use slash197\Reges\Support\Json;

/**
 * A message in its final wire form, header included, ready to be posted.
 *
 * It can be stored as JSON and sent later, which is what an outbox needs:
 * toJson() and fromJson() keep the message byte-for-byte equivalent.
 */
final readonly class Envelope
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public array $payload)
    {
        // Fails early on a payload that no endpoint accepts.
        $this->type();
    }

    public static function fromJson(string $json): self
    {
        $payload = Json::decodePreservingEmptyObjects($json);

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('A REGES message has to be a JSON object.');
        }

        return new self($payload);
    }

    public function toJson(): string
    {
        return Json::encode($this->payload);
    }

    public function type(): MessageType
    {
        $type = strtolower((string) ($this->payload['$type'] ?? ''));

        return MessageType::tryFrom($type)
            ?? throw new \InvalidArgumentException("Unsupported REGES payload type: {$type}");
    }

    public function messageId(): ?string
    {
        $messageId = $this->payload['header']['messageId'] ?? null;

        return is_string($messageId) ? $messageId : null;
    }

    public function operation(): ?string
    {
        $operation = $this->payload['header']['operation'] ?? null;

        return is_string($operation) ? $operation : null;
    }
}
