<?php

declare(strict_types=1);

namespace Slash197\Reges;

use Slash197\Reges\Exception\ApiException;
use Slash197\Reges\Http\Connection;
use Slash197\Reges\Http\Transport;
use Slash197\Reges\Support\Json;

/**
 * The reference lists REGES validates messages against (countries, COR
 * occupations, localities, legal grounds and so on). They change over time,
 * so keep a local copy and refresh it periodically.
 */
final class Nomenclators
{
    /**
     * @internal
     *
     * @param \Closure(): string $angajatorId
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly \Closure $angajatorId,
    ) {
    }

    /**
     * Every public nomenclator in one call, keyed by type. This is a large download.
     *
     * The employer's own bonus types are not public: use {@see tipSporAngajator()}.
     *
     * @return array<mixed>
     */
    public function all(): array
    {
        return $this->fetch(['tip' => 'toate'], authenticated: false);
    }

    /**
     * One nomenclator, e.g. "Cor", "Nationalitate" or "TemeiIncetare".
     *
     * @param array<string, scalar> $params Extra query parameters
     *
     * @return array<mixed>
     */
    public function get(string $type, array $params = []): array
    {
        return $this->fetch(['tip' => $type] + $params, authenticated: false);
    }

    /**
     * The bonus types this employer defined. Unlike the other nomenclators,
     * this one is per employer and needs authentication.
     *
     * @return array<mixed>
     */
    public function tipSporAngajator(): array
    {
        return $this->fetch(
            ['tip' => 'tipsporangajator', 'angajatorId' => ($this->angajatorId)()],
            authenticated: true,
        );
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<mixed>
     */
    private function fetch(array $query, bool $authenticated): array
    {
        $response = $this->connection->request(
            'GET',
            '/api/Nomenclator',
            $query,
            ['Accept' => 'application/json'],
            authenticated: $authenticated,
        );

        if ($response->getStatusCode() !== 200) {
            throw ApiException::fromResponse('nomenclator fetch', $response, Transport::requestId($response));
        }

        return Json::decodeArray((string) $response->getBody()) ?? [];
    }
}
