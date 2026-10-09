<?php

declare(strict_types=1);

namespace slash197\Reges\Auth;

final readonly class Token
{
    public function __construct(
        #[\SensitiveParameter]
        public string $accessToken,
        public ?\DateTimeImmutable $expiresAt = null,
        #[\SensitiveParameter]
        public ?string $refreshToken = null,
        public ?string $tokenType = null,
    ) {
    }

    /**
     * A token with no known expiry counts as expiring, so it is never reused.
     */
    public function expiresWithin(int $seconds, \DateTimeImmutable $now): bool
    {
        if ($this->expiresAt === null) {
            return true;
        }

        return $this->expiresAt <= $now->modify("+{$seconds} seconds");
    }

    /**
     * @return array{access_token: string, expires_at: string|null, refresh_token: string|null, token_type: string|null}
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'expires_at' => $this->expiresAt?->format(\DATE_ATOM),
            'refresh_token' => $this->refreshToken,
            'token_type' => $this->tokenType,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $expiresAt = is_string($data['expires_at'] ?? null) ? new \DateTimeImmutable($data['expires_at']) : null;

        return new self(
            (string) ($data['access_token'] ?? ''),
            $expiresAt,
            is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            is_string($data['token_type'] ?? null) ? $data['token_type'] : null,
        );
    }
}
