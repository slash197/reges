<?php

declare(strict_types=1);

namespace slash197\Reges\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use slash197\Reges\Exception\ApiException;
use slash197\Reges\Operation;
use slash197\Reges\Results\Result;
use slash197\Reges\Results\Results;
use slash197\Reges\Tests\Support\RegesTestCase;

final class ResultsTest extends RegesTestCase
{
    private const CAMEL = [
        'header' => ['messageId' => 'm-1', 'operation' => 'AdaugareContract'],
        'responseId' => 'r-1',
        'resultCode' => 'SUCCES',
        'resultCodeType' => 'Info',
        'resultSign' => 'sig',
        'resultDescription' => 'Operatie reusita',
        'resultRef' => 'ref-1',
        'resultSecRef' => 'sec-1',
        'relatedResultsExpected' => false,
    ];

    private const PASCAL = [
        'Header' => ['MessageId' => 'm-1', 'Operation' => 'AdaugareContract'],
        'ResponseId' => 'r-1',
        'ResultCode' => 'SUCCES',
        'ResultCodeType' => 'Info',
        'ResultSign' => 'sig',
        'ResultDescription' => 'Operatie reusita',
        'ResultRef' => 'ref-1',
        'ResultSecRef' => 'sec-1',
        'RelatedResultsExpected' => false,
    ];

    private const NESTED = [
        'header' => ['messageId' => 'm-1', 'operation' => 'AdaugareContract'],
        'responseId' => 'r-1',
        'resultSign' => 'sig',
        'result' => [
            'code' => 'SUCCES',
            'codeType' => 'Info',
            'description' => 'Operatie reusita',
            'ref' => 'ref-1',
            'secRef' => 'sec-1',
            'relatedResultsExpected' => false,
        ],
    ];

    private const NESTED_PASCAL = [
        'Header' => ['MessageId' => 'm-1', 'Operation' => 'AdaugareContract'],
        'ResponseId' => 'r-1',
        'ResultSign' => 'sig',
        'Result' => [
            'Code' => 'SUCCES',
            'CodeType' => 'Info',
            'Description' => 'Operatie reusita',
            'Ref' => 'ref-1',
            'SecRef' => 'sec-1',
            'RelatedResultsExpected' => false,
        ],
    ];

    /**
     * @param array<mixed> $payload
     */
    #[DataProvider('shapes')]
    public function testResultIsReadWhateverShapeItArrivesIn(array $payload): void
    {
        $result = Result::fromPayload($payload);

        self::assertSame('m-1', $result->messageId);
        self::assertSame('r-1', $result->responseId);
        self::assertSame('SUCCES', $result->code);
        self::assertSame('Info', $result->codeType);
        self::assertSame('sig', $result->sign);
        self::assertSame('Operatie reusita', $result->description);
        self::assertSame('ref-1', $result->ref);
        self::assertSame('sec-1', $result->secRef);
        self::assertFalse($result->relatedResultsExpected);
        self::assertSame('AdaugareContract', $result->operation);
        self::assertSame(Operation::AdaugareContract, $result->operation());
        self::assertTrue($result->isSuccess());
        self::assertSame($payload, $result->raw);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function shapes(): iterable
    {
        yield 'camelCase' => [self::CAMEL];
        yield 'PascalCase' => [self::PASCAL];
        yield 'nested' => [self::NESTED];
        yield 'nested PascalCase' => [self::NESTED_PASCAL];
    }

    public function testOnlySuccesCountsAsSuccess(): void
    {
        self::assertTrue(Result::fromPayload(['resultCode' => 'succes'])->isSuccess());
        self::assertFalse(Result::fromPayload(['resultCode' => 'FAIL'])->isSuccess());
        self::assertFalse(Result::fromPayload([])->isSuccess());
    }

    public function testMissingAndUnknownValuesAreNull(): void
    {
        $result = Result::fromPayload(['header' => ['operation' => 'InregistrareAngajator'], 'resultCode' => 500]);

        self::assertNull($result->messageId);
        self::assertNull($result->code);
        self::assertNull($result->relatedResultsExpected);
        self::assertSame('InregistrareAngajator', $result->operation);
        self::assertNull($result->operation());
    }

    public function testReadDoesNotAdvanceTheQueue(): void
    {
        $results = $this->results();
        $this->http->respond(200, self::CAMEL);

        $result = $results->read();

        self::assertSame('m-1', $result?->messageId);
        self::assertCount(1, $this->http->requests);
        self::assertSame('POST', $this->http->last()->getMethod());
        self::assertSame('https://api.dev.inspectiamuncii.org/api/Status/ReadMessage', (string) $this->http->last()->getUri());
        self::assertSame('Bearer test-token', $this->http->last()->getHeaderLine('Authorization'));
        self::assertSame('', (string) $this->http->last()->getBody());
    }

    public function testEmptyQueueReadsAsNull(): void
    {
        $results = $this->results();
        $this->http->respond(204);

        self::assertNull($results->read());
    }

    public function testCommitAdvancesTheQueue(): void
    {
        $results = $this->results();
        $this->http->respond(200);

        $results->commit();

        self::assertSame('https://api.dev.inspectiamuncii.org/api/Status/CommitRead', (string) $this->http->last()->getUri());
    }

    public function testConsumerIdIsPassedOnBothCalls(): void
    {
        $results = $this->results('payroll app/1');
        $this->http->respond(200, self::CAMEL)->respond(200);

        $results->consume(static fn () => null);

        self::assertSame('consumerId=payroll%20app%2F1', $this->http->requests[0]->getUri()->getQuery());
        self::assertSame('consumerId=payroll%20app%2F1', $this->http->requests[1]->getUri()->getQuery());
    }

    public function testConsumeCommitsAfterTheHandlerReturns(): void
    {
        $results = $this->results();
        $this->http->respond(200, self::CAMEL)->respond(200);
        $requestsSeenByHandler = null;

        $result = $results->consume(function (Result $result) use (&$requestsSeenByHandler): void {
            $requestsSeenByHandler = count($this->http->requests);
        });

        self::assertSame('m-1', $result?->messageId);
        self::assertSame(1, $requestsSeenByHandler, 'the handler runs before anything is committed');
        self::assertCount(2, $this->http->requests);
        self::assertStringEndsWith('/api/Status/CommitRead', (string) $this->http->last()->getUri());
    }

    /**
     * The point of the two-phase read: a crash while handling a result must not lose it.
     */
    public function testConsumeDoesNotCommitWhenTheHandlerThrows(): void
    {
        $results = $this->results();
        $this->http->respond(200, self::CAMEL);

        try {
            $results->consume(static fn () => throw new \DomainException('database is down'));
            self::fail('Expected the handler exception.');
        } catch (\DomainException) {
            self::assertCount(1, $this->http->requests);
            self::assertStringEndsWith('/api/Status/ReadMessage', (string) $this->http->last()->getUri());
        }
    }

    public function testConsumeOnAnEmptyQueueDoesNothing(): void
    {
        $results = $this->results();
        $this->http->respond(204);
        $called = false;

        self::assertNull($results->consume(function () use (&$called): void {
            $called = true;
        }));
        self::assertFalse($called);
        self::assertCount(1, $this->http->requests);
    }

    public function testFailedReadAndCommitThrow(): void
    {
        $results = $this->results();

        $this->http->respond(503, 'down');
        try {
            $results->read();
            self::fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            self::assertTrue($exception->isRetryable());
        }

        $this->http->respond(200, 'not json');
        try {
            $results->read();
            self::fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            self::assertSame('not json', $exception->body);
        }

        $this->http->respond(401);
        $this->expectException(ApiException::class);
        $results->commit();
    }

    public function testPollMessageIsNotOffered(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(Results::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        self::assertSame(['__construct', 'read', 'commit', 'consume'], $methods);
    }

    private function results(?string $consumerId = null): Results
    {
        return $this->reges()->results($consumerId);
    }
}
