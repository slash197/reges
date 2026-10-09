<?php

declare(strict_types=1);

namespace Slash197\Reges;

use Slash197\Reges\Data\Actiune;
use Slash197\Reges\Data\ContinutContract;
use Slash197\Reges\Data\DetaliiPropunereDetasare;
use Slash197\Reges\Data\DetaliiPropunereMutare;
use Slash197\Reges\Data\DocumentJustificativ;
use Slash197\Reges\Data\InfoSalariat;
use Slash197\Reges\Support\Json;

/**
 * One operation to report to REGES, described with typed objects.
 *
 * Each named constructor takes every part its message type can carry. Which
 * parts are actually sent depends on the operation: a part the operation does
 * not use is left out, and one it needs but did not get is reported by
 * validation when the message is sent.
 */
final readonly class Message
{
    private const ACTIUNE = [
        Operation::IncetareContract,
        Operation::CorectieIncetareContract,
        Operation::SuspendareContract,
        Operation::ModificareSuspendareContract,
        Operation::IncetareSuspendareContract,
        Operation::CorectieIncetareSuspendareContract,
        Operation::ReactivareContract,
        Operation::CorectieDetasareContract,
        Operation::PrelungireDetasareContract,
        Operation::ModificareDetasareContract,
        Operation::IncetareDetasareContract,
        Operation::CorectieIncetareDetasareContract,
    ];

    private const DOCUMENT_JUSTIFICATIV = [
        Operation::IncetareContract,
        Operation::CorectieIncetareContract,
        Operation::AnulareIncetareContract,
        Operation::SuspendareContract,
        Operation::ModificareSuspendareContract,
        Operation::IncetareSuspendareContract,
        Operation::CorectieIncetareSuspendareContract,
        Operation::AnulareSuspendareContract,
        Operation::ReactivareContract,
        Operation::AnulareReactivareContract,
    ];

    private const MOTIV_RADIERE = [
        Operation::RadiereContract,
        Operation::RadiereIstoricContract,
    ];

    private const DETASARE_REFERINTA = [
        Operation::AcceptarePropunereDetasareContract,
        Operation::RespingerePropunereDetasareContract,
        Operation::RadierePropunereDetasareContract,
        Operation::ModificarePropunereDetasareContract,
        Operation::IncetarePropunereDetasareContract,
    ];

    private const DETASARE_REFERINTA_CONTRACT = [
        Operation::PropunereDetasareContract,
        Operation::AcceptarePropunereDetasareContract,
        Operation::ModificarePropunereDetasareContract,
    ];

    private const DETASARE_DETALII = [
        Operation::PropunereDetasareContract,
        Operation::ModificarePropunereDetasareContract,
    ];

    private const DETASARE_CONTINUT = [
        Operation::PropunereDetasareContract,
        Operation::ModificarePropunereDetasareContract,
        Operation::AcceptarePropunereDetasareContract,
    ];

    private const DETASARE_NOTE_SURSA = [
        Operation::PropunereDetasareContract,
    ];

    private const DETASARE_NOTE_DESTINATIE = [
        Operation::AcceptarePropunereDetasareContract,
        Operation::RespingerePropunereDetasareContract,
    ];

    private const MUTARE_REFERINTA = [
        Operation::AcceptarePropunereMutareContract,
        Operation::RespingerePropunereMutareContract,
        Operation::RadierePropunereMutareContract,
    ];

    private const MUTARE_REFERINTA_CONTRACT = [
        Operation::PropunereMutareContract,
        Operation::AcceptarePropunereMutareContract,
    ];

    private const MUTARE_DETALII = [
        Operation::PropunereMutareContract,
    ];

    private const MUTARE_CONTINUT = [
        Operation::PropunereMutareContract,
        Operation::AcceptarePropunereMutareContract,
    ];

    private const MUTARE_NOTE_SURSA = [
        Operation::PropunereMutareContract,
    ];

    private const MUTARE_NOTE_DESTINATIE = [
        Operation::AcceptarePropunereMutareContract,
        Operation::RespingerePropunereMutareContract,
    ];

    /**
     * @param array<string, mixed> $parts
     */
    private function __construct(
        public Operation $operation,
        private array $parts,
        public ?string $messageId = null,
        public ?string $sessionId = null,
    ) {
    }

    /**
     * Registers, changes, corrects or strikes off an employee.
     *
     * @param string|null $referintaSalariat REGES id of the employee, needed by everything except InregistrareSalariat
     */
    public static function salariat(
        Operation $operation,
        ?InfoSalariat $info = null,
        ?string $referintaSalariat = null,
    ): self {
        self::expect($operation, MessageType::Salariat);

        return new self($operation, [
            'info' => $info,
            'referintaSalariat' => $referintaSalariat,
        ]);
    }

    /**
     * Adds a contract or reports something that happened to one.
     *
     * @param string|null $referintaContract REGES id of the contract. CorectieIstoricContract and
     *                                       RadiereIstoricContract take the id of the history entry instead
     *                                       (the "ref" of the result of the operation that created it).
     */
    public static function contract(
        Operation $operation,
        ?string $referintaContract = null,
        ?ContinutContract $continut = null,
        ?Actiune $actiune = null,
        ?DocumentJustificativ $documentJustificativ = null,
        ?string $motivRadiere = null,
    ): self {
        self::expect($operation, MessageType::Contract);

        return new self($operation, [
            'referintaContract' => $referintaContract,
            'continut' => $continut,
            'actiune' => $actiune,
            'documentJustificativ' => $documentJustificativ,
            'motivRadiere' => $motivRadiere,
        ]);
    }

    /**
     * Proposes moving a contract to another employer, or answers such a proposal.
     *
     * @param string|null $referinta         REGES id of the proposal (the "ref" of the result of PropunereMutareContract)
     * @param string|null $referintaContract REGES id of the contract
     */
    public static function propunereMutare(
        Operation $operation,
        ?string $referinta = null,
        ?string $referintaContract = null,
        ?DetaliiPropunereMutare $detalii = null,
        ?ContinutContract $continutContract = null,
        ?InfoSalariat $infoSalariat = null,
        ?string $noteSursa = null,
        ?string $noteDestinatie = null,
    ): self {
        self::expect($operation, MessageType::PropunereMutare);

        return new self($operation, compact(
            'referinta',
            'referintaContract',
            'detalii',
            'continutContract',
            'infoSalariat',
            'noteSursa',
            'noteDestinatie',
        ));
    }

    /**
     * Proposes seconding an employee to another employer, or answers, changes or ends such a proposal.
     *
     * @param string|null $referinta         REGES id of the proposal (the "ref" of the result of PropunereDetasareContract)
     * @param string|null $referintaContract REGES id of the contract
     */
    public static function propunereDetasare(
        Operation $operation,
        ?string $referinta = null,
        ?string $referintaContract = null,
        ?DetaliiPropunereDetasare $detalii = null,
        ?ContinutContract $continutContract = null,
        ?InfoSalariat $infoSalariat = null,
        ?string $noteSursa = null,
        ?string $noteDestinatie = null,
    ): self {
        self::expect($operation, MessageType::PropunereDetasare);

        return new self($operation, compact(
            'referinta',
            'referintaContract',
            'detalii',
            'continutContract',
            'infoSalariat',
            'noteSursa',
            'noteDestinatie',
        ));
    }

    /**
     * Sets the message id instead of having one generated. It is what a result is matched by.
     */
    public function withMessageId(string $messageId): self
    {
        return new self($this->operation, $this->parts, $messageId, $this->sessionId);
    }

    /**
     * Groups several messages under one session id instead of a generated one each.
     */
    public function withSessionId(string $sessionId): self
    {
        return new self($this->operation, $this->parts, $this->messageId, $sessionId);
    }

    public function type(): MessageType
    {
        return $this->operation->messageType();
    }

    /**
     * The message as REGES expects it, without the "$type" and header.
     *
     * @param \DateTimeInterface $now Reported as dataConsemnare by every operation except AdaugareContract
     *
     * @return array<string, mixed>
     */
    public function body(\DateTimeInterface $now): array
    {
        return match ($this->type()) {
            MessageType::Salariat => $this->salariatBody(),
            MessageType::Contract => $this->contractBody($now),
            MessageType::PropunereMutare => $this->propunereBody(
                $now,
                self::MUTARE_REFERINTA,
                self::MUTARE_REFERINTA_CONTRACT,
                self::MUTARE_DETALII,
                self::MUTARE_CONTINUT,
                self::MUTARE_NOTE_SURSA,
                self::MUTARE_NOTE_DESTINATIE,
            ),
            MessageType::PropunereDetasare => $this->propunereBody(
                $now,
                self::DETASARE_REFERINTA,
                self::DETASARE_REFERINTA_CONTRACT,
                self::DETASARE_DETALII,
                self::DETASARE_CONTINUT,
                self::DETASARE_NOTE_SURSA,
                self::DETASARE_NOTE_DESTINATIE,
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function salariatBody(): array
    {
        $body = [];

        if ($this->parts['info'] instanceof InfoSalariat) {
            $body['info'] = $this->parts['info']->toArray();
        }

        if ($this->parts['referintaSalariat']) {
            $body['referintaSalariat'] = [
                'id' => $this->parts['referintaSalariat'],
            ];
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function contractBody(\DateTimeInterface $now): array
    {
        $body = [];

        if ($this->parts['referintaContract']) {
            $body['referintaContract'] = [
                '$type' => 'referinta',
                'id' => $this->parts['referintaContract'],
            ];
        }

        $continut = $this->parts['continut'];
        if ($continut instanceof ContinutContract) {
            $adaugare = $this->operation === Operation::AdaugareContract;

            $body['continut'] = $continut->toArray(
                $adaugare ? ($continut->dataConsemnare ?? $continut->dataInceputContract) : $now,
            );
        }

        if ($this->parts['actiune'] instanceof Actiune && $this->operation->in(self::ACTIUNE)) {
            $body['actiune'] = $this->parts['actiune']->toArray();
        }

        if (
            $this->parts['documentJustificativ'] instanceof DocumentJustificativ
            && $this->operation->in(self::DOCUMENT_JUSTIFICATIV)
        ) {
            $body['documentJustificativ'] = $this->parts['documentJustificativ']->toArray();
        }

        if ($this->operation->in(self::MOTIV_RADIERE)) {
            $motiv = trim((string) $this->parts['motivRadiere']);
            if ($motiv !== '') {
                $body['motivRadiere'] = $motiv;
            }
        }

        return $body;
    }

    /**
     * @param list<Operation> $referinta
     * @param list<Operation> $referintaContract
     * @param list<Operation> $detalii
     * @param list<Operation> $continut
     * @param list<Operation> $noteSursa
     * @param list<Operation> $noteDestinatie
     *
     * @return array<string, mixed>
     */
    private function propunereBody(
        \DateTimeInterface $now,
        array $referinta,
        array $referintaContract,
        array $detalii,
        array $continut,
        array $noteSursa,
        array $noteDestinatie,
    ): array {
        $body = [];

        if ($this->parts['referinta'] && $this->operation->in($referinta)) {
            $body['referinta'] = [
                'id' => $this->parts['referinta'],
            ];
        }

        if ($this->parts['referintaContract'] && $this->operation->in($referintaContract)) {
            $body['referintaContract'] = [
                '$type' => 'referinta',
                'id' => $this->parts['referintaContract'],
            ];
        }

        if ($this->parts['detalii'] !== null && $this->operation->in($detalii)) {
            $body = array_merge($body, $this->parts['detalii']->toArray());
        }

        if ($this->operation->in($continut)) {
            if ($this->parts['continutContract'] instanceof ContinutContract) {
                $body['continutContract'] = $this->parts['continutContract']->toArray($now, propunere: true);
            }

            if ($this->parts['infoSalariat'] instanceof InfoSalariat) {
                $info = $this->parts['infoSalariat']->toArray();
                if ($info !== []) {
                    $body['infoSalariat'] = $info;
                }
            }
        }

        if ($this->parts['noteSursa'] && $this->operation->in($noteSursa)) {
            $body['noteSursa'] = $this->parts['noteSursa'];
        }

        if ($this->parts['noteDestinatie'] && $this->operation->in($noteDestinatie)) {
            $body['noteDestinatie'] = $this->parts['noteDestinatie'];
        }

        return Json::withoutNulls($body);
    }

    private static function expect(Operation $operation, MessageType $type): void
    {
        if ($operation->messageType() !== $type) {
            throw new \InvalidArgumentException(
                "{$operation->value} is a {$operation->messageType()->name} operation, not a {$type->name} one.",
            );
        }
    }
}
