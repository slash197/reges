<?php

declare(strict_types=1);

namespace slash197\Reges\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use slash197\Reges\Exception\ValidationException;
use slash197\Reges\Message;
use slash197\Reges\MessageType;
use slash197\Reges\Operation;
use slash197\Reges\Tests\Support\RegesTestCase;

/**
 * States, for every operation, which parts of a message it cannot go without.
 * The table is written out by hand so that a slip in the rule lists of the
 * validator shows up as a difference here.
 */
final class OperationRulesTest extends RegesTestCase
{
    private const PARTS = [
        'referinta',
        'referintaContract',
        'referintaSalariat',
        'info',
        'continut',
        'continutContract',
        'infoSalariat',
        'actiune',
        'documentJustificativ',
        'motivRadiere',
    ];

    /** "detalii" stands for the terms of a proposal, which sit at the top level of the message. */
    private const REQUIRED = [
        'InregistrareSalariat' => ['info'],
        'ModificareSalariat' => ['info', 'referintaSalariat'],
        'CorectieSalariat' => ['info', 'referintaSalariat'],
        'RadiereSalariat' => ['info', 'referintaSalariat'],

        'AdaugareContract' => ['continut'],
        'ModificareContract' => ['continut', 'referintaContract'],
        'CorectieContract' => ['continut', 'referintaContract'],
        'RadiereContract' => ['motivRadiere', 'referintaContract'],

        'IncetareContract' => ['actiune', 'documentJustificativ', 'referintaContract'],
        'CorectieIncetareContract' => ['actiune', 'documentJustificativ', 'referintaContract'],
        'AnulareIncetareContract' => ['documentJustificativ', 'referintaContract'],

        'ReactivareContract' => ['actiune', 'documentJustificativ', 'referintaContract'],
        'AnulareReactivareContract' => ['documentJustificativ', 'referintaContract'],

        'SuspendareContract' => ['actiune', 'documentJustificativ', 'referintaContract'],
        'ModificareSuspendareContract' => ['actiune', 'documentJustificativ', 'referintaContract'],
        'IncetareSuspendareContract' => ['actiune', 'documentJustificativ', 'referintaContract'],
        'CorectieIncetareSuspendareContract' => ['actiune', 'documentJustificativ', 'referintaContract'],
        'AnulareSuspendareContract' => ['documentJustificativ', 'referintaContract'],

        'CorectieDetasareContract' => ['actiune', 'referintaContract'],
        'PrelungireDetasareContract' => ['actiune', 'referintaContract'],
        'ModificareDetasareContract' => ['actiune', 'referintaContract'],
        'IncetareDetasareContract' => ['actiune', 'referintaContract'],
        'CorectieIncetareDetasareContract' => ['actiune', 'referintaContract'],
        'AnulareDetasareContract' => ['referintaContract'],
        'AnulareIncetareDetasareContract' => ['referintaContract'],
        'AnulareTransferContract' => ['referintaContract'],

        'CorectieIstoricContract' => ['continut', 'referintaContract'],
        'RadiereIstoricContract' => ['motivRadiere', 'referintaContract'],
        'AdaugareModificareInIstoricContract' => ['continut', 'referintaContract'],
        'AdaugareSuspendareInIstoricContract' => ['actiune', 'referintaContract'],
        'CorectieIstoricContractCuPropagare' => ['continut', 'referintaContract'],
        'AdaugareModificareInIstoricContractCuPropagare' => ['continut', 'referintaContract'],

        'PropunereDetasareContract' => ['continutContract', 'detalii', 'infoSalariat', 'referintaContract'],
        'ModificarePropunereDetasareContract' => ['continutContract', 'detalii', 'infoSalariat', 'referinta', 'referintaContract'],
        'AcceptarePropunereDetasareContract' => ['continutContract', 'infoSalariat', 'referinta', 'referintaContract'],
        'RespingerePropunereDetasareContract' => ['referinta'],
        'RadierePropunereDetasareContract' => ['referinta'],
        'IncetarePropunereDetasareContract' => ['referinta'],

        'PropunereMutareContract' => ['continutContract', 'detalii', 'infoSalariat', 'referintaContract'],
        'AcceptarePropunereMutareContract' => ['continutContract', 'infoSalariat', 'referinta', 'referintaContract'],
        'RespingerePropunereMutareContract' => ['referinta'],
        'RadierePropunereMutareContract' => ['referinta'],
    ];

    public function testTheTableCoversEveryOperation(): void
    {
        $operations = array_map(static fn (Operation $operation): string => $operation->value, Operation::cases());

        self::assertEqualsCanonicalizing($operations, array_keys(self::REQUIRED));
    }

    /**
     * @param list<string> $required
     */
    #[DataProvider('operations')]
    public function testAnEmptyMessageIsMissingExactlyWhatItsOperationNeeds(Operation $operation, array $required): void
    {
        $message = match ($operation->messageType()) {
            MessageType::Salariat => Message::salariat($operation),
            MessageType::Contract => Message::contract($operation),
            MessageType::PropunereMutare => Message::propunereMutare($operation),
            MessageType::PropunereDetasare => Message::propunereDetasare($operation),
        };

        try {
            $this->regesWithoutToken()->envelope($message);
            self::fail("{$operation->value} accepted an empty message.");
        } catch (ValidationException $exception) {
            $missing = [];
            foreach ($exception->fields() as $field) {
                $part = explode('.', $field)[0];
                $missing[in_array($part, self::PARTS, true) ? $part : 'detalii'] = true;
            }

            $missing = array_keys($missing);
            sort($missing);

            self::assertSame($required, $missing);
        }
    }

    /**
     * @return iterable<string, array{Operation, list<string>}>
     */
    public static function operations(): iterable
    {
        foreach (self::REQUIRED as $operation => $required) {
            yield $operation => [Operation::from($operation), $required];
        }
    }
}
