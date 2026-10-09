<?php

declare(strict_types=1);

namespace slash197\Reges\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use slash197\Reges\Exception\ApiException;

/**
 * Sends one HTTP request and logs what happened. Only metadata is logged:
 * REGES payloads carry personal data (CNP, address) that does not belong in logs.
 *
 * @internal
 */
final class Transport
{
    private const REQUEST_ID_HEADERS = ['trace-id', 'x-request-id', 'request-id', 'x-correlation-id'];

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        $endpoint = strtok($url, '?');

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            $this->logger->warning('REGES request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            throw new ApiException(
                "REGES request to {$endpoint} failed: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        $this->logger->info('REGES request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status' => $response->getStatusCode(),
            'request_id' => self::requestId($response),
        ]);

        return $response;
    }

    public static function requestId(ResponseInterface $response): ?string
    {
        foreach (self::REQUEST_ID_HEADERS as $header) {
            $value = $response->getHeaderLine($header);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
