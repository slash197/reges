<?php

declare(strict_types=1);

namespace slash197\Reges;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use slash197\Reges\Auth\Authenticator;
use slash197\Reges\Auth\InMemoryTokenStore;
use slash197\Reges\Auth\Token;
use slash197\Reges\Auth\TokenStore;
use slash197\Reges\Exception\ApiException;
use slash197\Reges\Exception\ValidationException;
use slash197\Reges\Http\Connection;
use slash197\Reges\Http\Transport;
use slash197\Reges\Results\Results;
use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Json;
use slash197\Reges\Support\SystemClock;
use slash197\Reges\Support\Uuid;
use slash197\Reges\Validation\NomenclatorLookup;
use slash197\Reges\Validation\Validator;

/**
 * Client for the REGES Online API, on behalf of one employer registry.
 */
final class Reges
{
    private readonly Authenticator $authenticator;
    private readonly Connection $connection;
    private readonly Validator $validator;
    private readonly ClockInterface $clock;
    private ?string $angajatorId = null;

    /**
     * @param ClientInterface        $httpClient        Any PSR-18 client. Configure timeouts on it.
     * @param TokenStore|null        $tokenStore        Where access tokens are kept; in memory by default
     * @param LoggerInterface|null   $logger            Receives request metadata, never message contents
     * @param NomenclatorLookup|null $nomenclatorLookup Enables checking values against your copy of the nomenclators
     */
    public function __construct(
        private readonly Config $config,
        private readonly Credentials $credentials,
        ClientInterface $httpClient,
        ?TokenStore $tokenStore = null,
        ?LoggerInterface $logger = null,
        ?NomenclatorLookup $nomenclatorLookup = null,
        ?ClockInterface $clock = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $logger ??= new NullLogger();
        $this->clock = $clock ?? new SystemClock();

        $factory = new Psr17Factory();
        $transport = new Transport($httpClient, $requestFactory ?? $factory, $streamFactory ?? $factory, $logger);

        $this->authenticator = new Authenticator(
            $config,
            $credentials,
            $transport,
            $tokenStore ?? new InMemoryTokenStore(),
            $this->clock,
            $logger,
        );
        $this->connection = new Connection($config, $transport, $this->authenticator);
        $this->validator = new Validator($nomenclatorLookup);
    }

    /**
     * Sends a message and returns the receipt REGES answered with.
     *
     * The receipt only confirms that the message was queued. Whether it was
     * accepted is reported later through {@see results()}.
     *
     * @param string|null $user The person on whose behalf the message is sent; falls back to Config::$user.
     *                          Ignored for an Envelope, whose header is already final.
     *
     * @throws ValidationException When the message lacks something its operation needs
     * @throws ApiException        When REGES did not accept the submission; see ApiException::isRetryable()
     */
    public function send(Message|Envelope $message, ?string $user = null): Receipt
    {
        $envelope = $message instanceof Envelope ? $message : $this->envelope($message, $user);

        $response = $this->connection->request(
            'POST',
            $envelope->type()->path(),
            headers: ['Content-Type' => 'application/json'],
            body: $envelope->toJson(),
        );

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw ApiException::fromResponse('message submission', $response, Transport::requestId($response));
        }

        $body = Json::decodeArray((string) $response->getBody()) ?? [];
        $responseId = $body['responseId'] ?? $body['ResponseId'] ?? null;

        return new Receipt(
            $envelope->messageId(),
            is_string($responseId) ? $responseId : null,
            $status,
            $body,
        );
    }

    /**
     * Validates a message and puts it in its final wire form without sending
     * it, for callers that queue messages: store Envelope::toJson(), then
     * send Envelope::fromJson() when its turn comes.
     *
     * @throws ValidationException
     */
    public function envelope(Message $message, ?string $user = null): Envelope
    {
        $user = trim((string) ($user ?? $this->config->user));
        if ($user === '') {
            throw new \InvalidArgumentException('REGES needs the name of the user sending the message.');
        }

        $now = $this->clock->now();
        $body = $message->body($now);

        // REGES identifies the author by the API username, which is a UUID.
        $context = array_filter([
            'authorId' => $this->credentials->username,
            'sessionId' => $message->sessionId,
        ]);

        $this->validator->validate($message->operation, $body, $context);

        $header = [
            'messageId' => $message->messageId ?? Uuid::v4(),
            'clientApplication' => $this->config->clientApplication,
            'version' => $this->config->clientVersion,
            'operation' => $message->operation->value,
            'sessionId' => $context['sessionId'] ?? Uuid::v4(),
            'user' => $user,
            'timestamp' => Dates::format($now),
        ];

        if (!empty($context['authorId'])) {
            $header['authorId'] = $context['authorId'];
        }

        return new Envelope(array_merge([
            '$type' => $message->type()->value,
            'header' => $header,
        ], $body));
    }

    /**
     * The queue of results for the messages sent to this registry.
     *
     * @param string|null $consumerId Reads the queue as a separate consumer with its own position. Applications
     *                                sharing one registry each need their own, or they take each other's results.
     */
    public function results(?string $consumerId = null): Results
    {
        return new Results($this->connection, $consumerId);
    }

    public function profile(): Profile
    {
        $response = $this->connection->request('GET', '/api/Profile', headers: ['Accept' => 'application/json']);

        if ($response->getStatusCode() !== 200) {
            throw ApiException::fromResponse('profile fetch', $response, Transport::requestId($response));
        }

        $payload = Json::decodeArray((string) $response->getBody()) ?? [];
        $angajatorId = $payload['referintaAngajator']['id'] ?? null;
        $angajatorId = is_string($angajatorId) && trim($angajatorId) !== '' ? trim($angajatorId) : null;

        if ($angajatorId !== null) {
            $this->angajatorId = $angajatorId;
        }

        return new Profile($angajatorId, $payload);
    }

    public function nomenclators(): Nomenclators
    {
        return new Nomenclators($this->connection, $this->angajatorId(...));
    }

    public function bonusTypes(): BonusTypes
    {
        return new BonusTypes($this->connection, $this->angajatorId(...));
    }

    /**
     * Requests a new access token now, whatever is in the token store. Normally
     * not needed: tokens are obtained and renewed on demand.
     */
    public function authenticate(): Token
    {
        return $this->authenticator->authenticate();
    }

    /**
     * The REGES id of the employer, fetched from the profile once per instance.
     */
    public function angajatorId(): string
    {
        return $this->angajatorId
            ?? $this->profile()->angajatorId
            ?? throw new ApiException('The REGES profile does not include an employer id.');
    }
}
