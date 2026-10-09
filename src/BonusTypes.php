<?php

declare(strict_types=1);

namespace slash197\Reges;

use Psr\Http\Message\ResponseInterface;
use slash197\Reges\Exception\ApiException;
use slash197\Reges\Http\Connection;
use slash197\Reges\Http\Transport;
use slash197\Reges\Support\Json;

/**
 * Manages the bonus types an employer defines for itself (TipSporAngajator),
 * which contracts then refer to in sporuriSalariu.
 *
 * Unlike the rest of the API, this endpoint takes XML and answers in either
 * JSON or XML.
 */
final class BonusTypes
{
    private const PATH = '/api/Nomenclator/tipsporangajator';

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

    public function create(string $name): BonusType
    {
        $data = $this->parse($this->send('POST', $this->payload($name)));

        if (!$data['id']) {
            throw new ApiException('REGES did not return an id for the new bonus type.');
        }

        return new BonusType($data['id'], $data['name'] ?: $name, $data['raw']);
    }

    public function update(string $id, string $name): BonusType
    {
        $data = $this->parse($this->send('POST', $this->payload($name, $id)));

        return new BonusType($data['id'] ?: $id, $data['name'] ?: $name, $data['raw']);
    }

    /**
     * @param string $name The current name of the bonus type; REGES wants the full record to delete it
     */
    public function delete(string $id, string $name): void
    {
        $this->send('DELETE', $this->payload($name, $id));
    }

    private function payload(string $name, ?string $id = null): string
    {
        $idTag = $id ? '<Id>' . self::escape($id) . "</Id>\n" : '';

        return sprintf(
            "<TipSporAngajator>\n%s<Nume>%s</Nume>\n<AngajatorId>%s</AngajatorId>\n</TipSporAngajator>",
            $idTag,
            self::escape($name),
            self::escape(($this->angajatorId)()),
        );
    }

    private function send(string $method, string $payload): ResponseInterface
    {
        $response = $this->connection->request(
            $method,
            self::PATH,
            headers: ['Accept' => 'text/xml', 'Content-Type' => 'text/xml'],
            body: $payload,
        );

        if ($response->getStatusCode() !== 200) {
            throw ApiException::fromResponse('bonus type request', $response, Transport::requestId($response));
        }

        return $response;
    }

    /**
     * @return array{id: string|null, name: string|null, raw: array<mixed>}
     */
    private function parse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $json = Json::decodeArray($body);

        if ($json !== null) {
            return [
                'id' => isset($json['id']) ? (string) $json['id'] : null,
                'name' => isset($json['name']) ? (string) $json['name'] : null,
                'raw' => $json,
            ];
        }

        $id = null;
        $name = null;
        $raw = [];

        $body = trim($body);
        if ($body !== '' && str_starts_with($body, '<')) {
            $xml = @simplexml_load_string($body, \SimpleXMLElement::class, \LIBXML_NOCDATA | \LIBXML_NONET);
            if ($xml) {
                $id = self::xmlValue($xml, ['Id', 'id']);
                $name = self::xmlValue($xml, ['Nume', 'nume', 'Name', 'name']);
                $raw = ['id' => $id, 'name' => $name];
            }
        }

        return ['id' => $id, 'name' => $name, 'raw' => $raw];
    }

    /**
     * @param list<string> $keys
     */
    private static function xmlValue(\SimpleXMLElement $xml, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($xml->{$key})) {
                $value = trim((string) $xml->{$key});
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
    }
}
