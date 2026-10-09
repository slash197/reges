<?php

declare(strict_types=1);

namespace Slash197\Reges\Results;

use Slash197\Reges\Operation;

/**
 * The outcome of a message, read from the employer's result queue.
 *
 * What "ref" identifies depends on the operation:
 *  - InregistrareSalariat, AdaugareContract: the new employee or contract;
 *  - PropunereMutareContract, PropunereDetasareContract: the new proposal;
 *  - AcceptarePropunereMutareContract, AcceptarePropunereDetasareContract:
 *    the contract REGES created in the destination employer's registry;
 *  - any other contract operation: the history entry the operation created.
 *    Keep it: CorectieIstoricContract and RadiereIstoricContract need it.
 * "secRef" is the destination employer's employee reference on an accepted
 * proposal. It has nothing to do with history.
 */
final readonly class Result
{
    /**
     * @param string|null  $messageId              Id of the message this is the result of
     * @param string|null  $operation              Operation name as sent by REGES, see {@see operation()}
     * @param bool|null    $relatedResultsExpected True when more results will follow for the same message
     * @param array<mixed> $raw                    The decoded result as received
     */
    public function __construct(
        public ?string $messageId,
        public ?string $responseId,
        public ?string $code,
        public ?string $codeType,
        public ?string $description,
        public ?string $ref,
        public ?string $secRef,
        public ?bool $relatedResultsExpected,
        public ?string $operation,
        public ?string $sign,
        public array $raw,
    ) {
    }

    /**
     * REGES is not consistent about the shape of a result: keys arrive in
     * camelCase or PascalCase, flat ("resultCode") or nested ("result.code").
     *
     * @param array<mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            messageId: self::string($payload, ['header', 'messageId'], ['Header', 'MessageId']),
            responseId: self::string($payload, ['responseId'], ['ResponseId']),
            code: self::string($payload, ['resultCode'], ['ResultCode'], ['result', 'code'], ['Result', 'Code']),
            codeType: self::string($payload, ['resultCodeType'], ['ResultCodeType'], ['result', 'codeType'], ['Result', 'CodeType']),
            description: self::string($payload, ['resultDescription'], ['ResultDescription'], ['result', 'description'], ['Result', 'Description']),
            ref: self::string($payload, ['resultRef'], ['ResultRef'], ['result', 'ref'], ['Result', 'Ref']),
            secRef: self::string($payload, ['resultSecRef'], ['ResultSecRef'], ['result', 'secRef'], ['Result', 'SecRef']),
            relatedResultsExpected: self::bool(
                $payload,
                ['relatedResultsExpected'],
                ['RelatedResultsExpected'],
                ['result', 'relatedResultsExpected'],
                ['Result', 'RelatedResultsExpected'],
            ),
            operation: self::string($payload, ['header', 'operation'], ['Header', 'Operation']),
            sign: self::string($payload, ['resultSign'], ['ResultSign']),
            raw: $payload,
        );
    }

    public function isSuccess(): bool
    {
        return $this->code !== null && strtoupper($this->code) === 'SUCCES';
    }

    public function operation(): ?Operation
    {
        return $this->operation === null ? null : Operation::tryFrom($this->operation);
    }

    /**
     * @param array<mixed> $payload
     * @param list<string> ...$paths
     */
    private static function string(array $payload, array ...$paths): ?string
    {
        foreach ($paths as $path) {
            $value = self::get($payload, $path);
            if (is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $payload
     * @param list<string> ...$paths
     */
    private static function bool(array $payload, array ...$paths): ?bool
    {
        foreach ($paths as $path) {
            $value = self::get($payload, $path);
            if (is_bool($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $payload
     * @param list<string> $path
     */
    private static function get(array $payload, array $path): mixed
    {
        $value = $payload;

        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
