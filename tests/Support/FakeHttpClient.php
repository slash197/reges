<?php

declare(strict_types=1);

namespace Slash197\Reges\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Answers requests from a queue and remembers what it was asked.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $queue = [];

    /**
     * @param array<mixed>|string   $body    An array is sent as JSON
     * @param array<string, string> $headers
     */
    public function respond(int $status = 200, array|string $body = '', array $headers = []): self
    {
        $this->queue[] = new Response($status, $headers, is_array($body) ? json_encode($body, \JSON_THROW_ON_ERROR) : $body);

        return $this;
    }

    public function fail(string $message = 'Connection refused'): self
    {
        $this->queue[] = new class ($message) extends \RuntimeException implements ClientExceptionInterface {
        };

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        $next = array_shift($this->queue)
            ?? throw new \LogicException("No response queued for {$request->getMethod()} {$request->getUri()}");

        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    public function last(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)]
            ?? throw new \LogicException('No request was sent.');
    }

    /**
     * @return array<mixed>
     */
    public function lastJson(): array
    {
        return json_decode((string) $this->last()->getBody(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
