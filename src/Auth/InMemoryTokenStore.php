<?php

declare(strict_types=1);

namespace Slash197\Reges\Auth;

final class InMemoryTokenStore implements TokenStore
{
    /** @var array<string, Token> */
    private array $tokens = [];

    public function get(string $key): ?Token
    {
        return $this->tokens[$key] ?? null;
    }

    public function put(string $key, Token $token): void
    {
        $this->tokens[$key] = $token;
    }
}
