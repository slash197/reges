<?php

declare(strict_types=1);

namespace slash197\Reges\Http;

use Psr\Http\Message\ResponseInterface;
use slash197\Reges\Auth\Authenticator;
use slash197\Reges\Config;

/**
 * Requests against the REGES API host, with the bearer token added.
 *
 * @internal
 */
final class Connection
{
    public function __construct(
        private readonly Config $config,
        private readonly Transport $transport,
        private readonly Authenticator $authenticator,
    ) {
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        ?string $body = null,
        bool $authenticated = true,
    ): ResponseInterface {
        $url = $this->config->apiUrl . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
        }

        if ($authenticated) {
            $headers['Authorization'] = 'Bearer ' . $this->authenticator->accessToken();
        }

        return $this->transport->send($method, $url, $headers, $body);
    }
}
