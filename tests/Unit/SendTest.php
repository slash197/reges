<?php

declare(strict_types=1);

namespace Slash197\Reges\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Slash197\Reges\Envelope;
use Slash197\Reges\Exception\ApiException;
use Slash197\Reges\Exception\ValidationException;
use Slash197\Reges\Message;
use Slash197\Reges\MessageType;
use Slash197\Reges\Operation;
use Slash197\Reges\Support\Uuid;
use Slash197\Reges\Tests\Support\RegesTestCase;

final class SendTest extends RegesTestCase
{
    public function testEveryOperationHasAMessageType(): void
    {
        $counts = [];
        foreach (Operation::cases() as $operation) {
            $counts[$operation->messageType()->name] = ($counts[$operation->messageType()->name] ?? 0) + 1;
        }

        self::assertCount(42, Operation::cases());
        self::assertSame(['Contract' => 28, 'PropunereDetasare' => 6, 'PropunereMutare' => 4, 'Salariat' => 4], $this->sorted($counts));
    }

    /**
     * Proposals posted to /api/Contract intermittently come back as a bare 500.
     */
    #[DataProvider('endpoints')]
    public function testMessageIsPostedToTheEndpointOfItsType(Message $message, string $path): void
    {
        $reges = $this->reges();
        $this->http->respond(200, ['responseId' => 'r-1']);

        $reges->send($message);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.dev.inspectiamuncii.org' . $path, (string) $request->getUri());
        self::assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }

    /**
     * @return iterable<string, array{Message, string}>
     */
    public static function endpoints(): iterable
    {
        yield 'salariat' => [Message::salariat(Operation::RadiereSalariat, (new self('x'))->info(), self::SALARIAT_ID), '/api/Salariat'];
        yield 'contract' => [Message::contract(Operation::AnulareTransferContract, self::CONTRACT_ID), '/api/Contract'];
        yield 'mutare' => [Message::propunereMutare(Operation::RadierePropunereMutareContract, self::PROPUNERE_ID), '/api/Mutare/Propunere'];
        yield 'detasare' => [Message::propunereDetasare(Operation::RadierePropunereDetasareContract, self::PROPUNERE_ID), '/api/Detasare/Propunere'];
    }

    public function testReceiptCarriesTheMessageAndResponseIds(): void
    {
        $reges = $this->reges();
        $this->http->respond(202, ['ResponseId' => 'r-9', 'extra' => true]);

        $receipt = $reges->send(Message::contract(Operation::AnulareTransferContract, self::CONTRACT_ID));

        self::assertTrue(Uuid::isValid($receipt->messageId));
        self::assertSame($this->http->lastJson()['header']['messageId'], $receipt->messageId);
        self::assertSame('r-9', $receipt->responseId);
        self::assertSame(202, $receipt->statusCode);
        self::assertSame(['ResponseId' => 'r-9', 'extra' => true], $receipt->raw);
    }

    public function testTypeIsTheFirstKeyOfTheMessageAndOfPolymorphicObjects(): void
    {
        $envelope = $this->regesWithoutToken()->envelope(
            Message::contract(Operation::ModificareContract, self::CONTRACT_ID, $this->continut()),
        );

        self::assertSame('$type', array_key_first($envelope->payload));
        self::assertSame('$type', array_key_first($envelope->payload['referintaContract']));
        self::assertSame('$type', array_key_first($envelope->payload['continut']));
        self::assertSame('$type', array_key_first($envelope->payload['continut']['referintaSalariat']));
        self::assertStringStartsWith('{"$type":"contract","header":{', $envelope->toJson());
    }

    public function testHeaderIdentifiesTheSenderAndAuthor(): void
    {
        $envelope = $this->regesWithoutToken()->envelope(
            Message::contract(Operation::AnulareTransferContract, self::CONTRACT_ID),
            user: '  Maria  ',
        );

        $header = $envelope->payload['header'];
        self::assertSame(
            ['messageId', 'clientApplication', 'version', 'operation', 'sessionId', 'user', 'timestamp', 'authorId'],
            array_keys($header),
        );
        self::assertSame('Payroll', $header['clientApplication']);
        self::assertSame('1.0', $header['version']);
        self::assertSame('AnulareTransferContract', $header['operation']);
        self::assertSame('Maria', $header['user']);
        self::assertSame(self::NOW, $header['timestamp']);
        self::assertSame(self::USERNAME, $header['authorId']);
        self::assertTrue(Uuid::isValid($header['messageId']));
        self::assertTrue(Uuid::isValid($header['sessionId']));
        self::assertNotSame($header['messageId'], $header['sessionId']);
    }

    public function testUsernameThatIsNotAUuidIsRejectedAsAuthorId(): void
    {
        try {
            $this->regesWithoutToken(username: 'not-a-uuid')->envelope(
                Message::contract(Operation::AnulareTransferContract, self::CONTRACT_ID),
            );
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame(['authorId' => 'uuid'], $exception->errors);
        }
    }

    public function testUserIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->regesWithoutToken()->envelope(
            Message::contract(Operation::AnulareTransferContract, self::CONTRACT_ID),
            user: '  ',
        );
    }

    public function testOperationOfAnotherMessageTypeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('InregistrareSalariat is a Salariat operation');

        Message::contract(Operation::InregistrareSalariat);
    }

    /**
     * stareCurenta has to reach REGES as "{}". A plain json_decode/json_encode
     * round trip, as an outbox would do, turns it into "[]".
     */
    public function testEnvelopeSurvivesBeingStoredAsJson(): void
    {
        $reges = $this->reges();
        $envelope = $reges->envelope(Message::contract(Operation::ModificareContract, self::CONTRACT_ID, $this->continut(sporuriSalariu: [])));

        $json = $envelope->toJson();
        self::assertStringContainsString('"stareCurenta":{}', $json);
        self::assertStringContainsString('"sporuriSalariu":[]', $json);
        self::assertStringContainsString('"stareCurenta":[]', (string) json_encode(json_decode($json, true)), 'the naive round trip is the bug');

        $restored = Envelope::fromJson($json);
        self::assertSame($json, $restored->toJson());
        self::assertSame(MessageType::Contract, $restored->type());
        self::assertSame($envelope->messageId(), $restored->messageId());
        self::assertSame('ModificareContract', $restored->operation());

        $this->http->respond(200, ['responseId' => 'r-1']);
        $receipt = $reges->send($restored);

        self::assertSame($json, (string) $this->http->last()->getBody());
        self::assertSame($envelope->messageId(), $receipt->messageId);
    }

    public function testEnvelopeOfUnknownTypeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported REGES payload type: angajator');

        Envelope::fromJson('{"$type":"Angajator","header":{}}');
    }

    public function testEnvelopeTypeIsCaseInsensitive(): void
    {
        self::assertSame(MessageType::PropunereMutare, Envelope::fromJson('{"$type":"PropunereMutareContract"}')->type());
    }

    #[DataProvider('failures')]
    public function testFailedSubmissionSaysWhetherRetryingCanHelp(int $status, bool $retryable): void
    {
        $reges = $this->reges();
        $this->http->respond($status, 'schema error', ['trace-id' => 'abc']);

        try {
            $reges->send(Message::contract(Operation::AnulareTransferContract, self::CONTRACT_ID));
            self::fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            self::assertSame($status, $exception->statusCode);
            self::assertSame('schema error', $exception->body);
            self::assertSame('abc', $exception->requestId);
            self::assertSame($retryable, $exception->isRetryable());
        }
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function failures(): iterable
    {
        yield 'bad request' => [400, false];
        yield 'unauthorized' => [401, false];
        yield 'not found' => [404, false];
        yield 'request timeout' => [408, true];
        yield 'too many requests' => [429, true];
        yield 'server error' => [500, true];
        yield 'bad gateway' => [502, true];
    }

    public function testNetworkFailureIsRetryable(): void
    {
        $reges = $this->reges();
        $this->http->fail('Connection refused');

        try {
            $reges->send(Message::contract(Operation::AnulareTransferContract, self::CONTRACT_ID));
            self::fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            self::assertNull($exception->statusCode);
            self::assertTrue($exception->isRetryable());
            self::assertStringContainsString('Connection refused', $exception->getMessage());
        }
    }

    public function testInvalidMessageIsNotSent(): void
    {
        $reges = $this->reges();

        try {
            $reges->send(Message::contract(Operation::ModificareContract));
            self::fail('Expected a ValidationException.');
        } catch (ValidationException) {
            self::assertSame([], $this->http->requests);
        }
    }

    /**
     * @param array<string, int> $values
     *
     * @return array<string, int>
     */
    private function sorted(array $values): array
    {
        ksort($values);

        return $values;
    }
}
