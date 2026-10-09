<?php

declare(strict_types=1);

namespace Slash197\Reges\Results;

use Slash197\Reges\Exception\ApiException;
use Slash197\Reges\Http\Connection;
use Slash197\Reges\Http\Transport;
use Slash197\Reges\Support\Json;

/**
 * The employer's queue of results, read one at a time in two steps.
 *
 * read() returns the next result without moving past it, and commit() moves
 * past it. Committing only after the result is safely stored means a crash in
 * between costs nothing: the next read() returns the same result again.
 * (REGES also offers PollMessage, which does both at once and so loses the
 * result on such a crash. It is deliberately not available here.)
 */
final class Results
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ?string $consumerId = null,
    ) {
    }

    /**
     * The next unread result, or null when the queue is empty. Repeated calls
     * return the same result until commit() is called.
     */
    public function read(): ?Result
    {
        $response = $this->connection->request('POST', '/api/Status/ReadMessage', $this->query());
        $status = $response->getStatusCode();

        if ($status === 204) {
            return null;
        }

        if ($status !== 200) {
            throw ApiException::fromResponse('result read', $response, Transport::requestId($response));
        }

        $payload = Json::decodeArray((string) $response->getBody());
        if ($payload === null) {
            throw new ApiException(
                'REGES result read returned something that is not a JSON object.',
                $status,
                (string) $response->getBody(),
                Transport::requestId($response),
            );
        }

        return Result::fromPayload($payload);
    }

    /**
     * Moves past the result last returned by read().
     */
    public function commit(): void
    {
        $response = $this->connection->request('POST', '/api/Status/CommitRead', $this->query());
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw ApiException::fromResponse('result commit', $response, Transport::requestId($response));
        }
    }

    /**
     * Reads the next result, hands it to $handler and commits once the handler
     * returns. If the handler throws, nothing is committed and the same result
     * comes back on the next call.
     *
     * @param callable(Result): mixed $handler Should store the result durably before returning
     *
     * @return Result|null The result that was handled, or null when the queue was empty
     */
    public function consume(callable $handler): ?Result
    {
        $result = $this->read();

        if ($result === null) {
            return null;
        }

        $handler($result);
        $this->commit();

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function query(): array
    {
        return $this->consumerId ? ['consumerId' => $this->consumerId] : [];
    }
}
