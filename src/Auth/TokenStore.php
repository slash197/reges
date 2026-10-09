<?php

declare(strict_types=1);

namespace Slash197\Reges\Auth;

/**
 * Where access tokens are kept between requests. Implement it on top of your
 * cache or database to avoid requesting a new token in every process.
 */
interface TokenStore
{
    /**
     * @param string $key Identifies one set of credentials on one environment
     */
    public function get(string $key): ?Token;

    public function put(string $key, Token $token): void;
}
