<?php

declare(strict_types=1);

namespace slash197\Reges\Auth;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use slash197\Reges\Config;
use slash197\Reges\Credentials;
use slash197\Reges\Exception\AuthenticationException;
use slash197\Reges\Http\Transport;
use slash197\Reges\Support\Json;

/**
 * @internal
 */
final class Authenticator
{
    private const EXPIRY_WINDOW_SECONDS = 60;

    public function __construct(
        private readonly Config $config,
        private readonly Credentials $credentials,
        private readonly Transport $transport,
        private readonly TokenStore $store,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * A stored token that is still good for at least a minute, or a fresh one.
     */
    public function accessToken(): string
    {
        $stored = $this->store->get($this->storeKey());

        if (
            $stored !== null
            && trim($stored->accessToken) !== ''
            && !$stored->expiresWithin(self::EXPIRY_WINDOW_SECONDS, $this->clock->now())
        ) {
            return trim($stored->accessToken);
        }

        return $this->authenticate()->accessToken;
    }

    public function authenticate(): Token
    {
        if ($this->credentials->username === '' || $this->credentials->password === '') {
            throw new AuthenticationException('REGES credentials are missing.', error: 'missing_credentials');
        }

        $response = $this->transport->send(
            'POST',
            $this->config->tokenUrl,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'grant_type' => 'password',
                'client_id' => $this->config->clientId,
                'client_secret' => $this->config->clientSecret,
                'username' => $this->credentials->username,
                'password' => $this->credentials->password,
            ]),
        );

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = Json::decodeArray($body) ?? [];

        $accessToken = $data['access_token'] ?? null;
        $hasToken = is_string($accessToken) && trim($accessToken) !== '';

        $this->logger->info('REGES auth response', [
            'status' => $status,
            'expires_in' => $data['expires_in'] ?? null,
            'token_type' => $data['token_type'] ?? null,
            'scope' => $data['scope'] ?? null,
            'has_token' => $hasToken,
            'error' => $data['error'] ?? null,
        ]);

        if ($status !== 200 || !$hasToken) {
            $error = is_string($data['error'] ?? null) ? $data['error'] : null;
            $description = is_string($data['error_description'] ?? null) ? $data['error_description'] : null;

            throw new AuthenticationException(
                'REGES authentication failed' . ($error !== null ? ": {$error}" : " with HTTP {$status}") . '.',
                $status,
                $error ?? ($data === [] ? $body : null),
                $description,
            );
        }

        $token = new Token(
            trim($accessToken),
            $this->expiresAt($data),
            is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            is_string($data['token_type'] ?? null) ? $data['token_type'] : null,
        );

        $this->store->put($this->storeKey(), $token);

        return $token;
    }

    /**
     * @param array<mixed> $data
     */
    private function expiresAt(array $data): ?\DateTimeImmutable
    {
        if (!empty($data['expires_at']) && is_string($data['expires_at'])) {
            try {
                return new \DateTimeImmutable($data['expires_at']);
            } catch (\Exception) {
                // fall through to expires_in
            }
        }

        if (isset($data['expires_in'])) {
            $seconds = (int) $data['expires_in'];

            return $this->clock->now()->modify("+{$seconds} seconds");
        }

        return null;
    }

    private function storeKey(): string
    {
        return 'reges:' . hash('sha256', implode('|', [
            $this->config->tokenUrl,
            $this->config->clientId,
            $this->credentials->username,
        ]));
    }
}
