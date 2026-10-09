<?php

declare(strict_types=1);

namespace slash197\Reges\Data;

use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Json;
use slash197\Reges\Support\Uuid;

/**
 * The content of an employment contract. Every field is optional here so that
 * a message missing several of them is reported in one go by validation.
 */
final readonly class ContinutContract
{
    /**
     * @param string|null            $referintaSalariat  REGES id of the employee
     * @param \DateTimeInterface|null $dataConsemnare    The date the content takes effect. Used by AdaugareContract,
     *                                                   where it defaults to dataInceputContract, and by the
     *                                                   operations on the contract's history, which place a change
     *                                                   in the past. Every other operation reports the moment the
     *                                                   message is built, because REGES rejects a value that does
     *                                                   not advance from one message to the next.
     * @param string|null            $tipNorma           Either norm code set is accepted, see {@see Norma}
     * @param list<SporSalariu>|null $sporuriSalariu     Null leaves bonuses unchanged, an empty list clears them
     * @param int|null               $localitateLocMunca SIRUTA code of the workplace locality
     */
    public function __construct(
        public ?string $referintaSalariat = null,
        public ?Cor $cor = null,
        public ?\DateTimeInterface $dataContract = null,
        public ?\DateTimeInterface $dataInceputContract = null,
        public ?string $numarContract = null,
        public int|float|null $salariu = null,
        public ?TimpMunca $timpMunca = null,
        public ?string $tipContract = null,
        public ?string $tipDurata = null,
        public ?string $tipNorma = null,
        public ?bool $radiat = false,
        public ?\DateTimeInterface $dataSfarsitContract = null,
        public ?string $exceptieDataSfarsit = null,
        public ?array $sporuriSalariu = null,
        public ?string $tipLocMunca = null,
        public ?string $judetLocMunca = null,
        public ?int $localitateLocMunca = null,
        public ?string $nivelStudii = null,
        public ?\DateTimeInterface $dataConsemnare = null,
    ) {
    }

    /**
     * @param \DateTimeInterface|null $dataConsemnare The value to report, already resolved for the operation
     * @param bool                    $propunere      True when the content is part of a mutare/detasare proposal
     *
     * @return array<string, mixed>
     */
    public function toArray(?\DateTimeInterface $dataConsemnare, bool $propunere = false): array
    {
        $continut = [
            '$type' => 'continutContract',
            'referintaSalariat' => $this->referintaSalariat ? [
                '$type' => 'referinta',
                'id' => $this->referintaSalariat,
            ] : null,
            'cor' => $this->cor?->toArray(),
            'dataConsemnare' => Dates::format($dataConsemnare),
            'dataContract' => Dates::format($this->dataContract),
            'dataInceputContract' => Dates::format($this->dataInceputContract),
            'dataSfarsitContract' => Dates::format($this->dataSfarsitContract),
            'exceptieDataSfarsit' => $this->exceptieDataSfarsit,
            'numarContract' => $this->numarContract,
            'radiat' => $this->radiat,
            'salariu' => $this->salariu,
            'timpMunca' => $this->timpMunca?->toArray(),
            'sporuriSalariu' => SporSalariu::listToArray($this->sporuriSalariu),
            'tipContract' => $this->tipContract,
            'tipDurata' => $this->tipDurata,
            'tipNorma' => Norma::tipNorma($this->tipNorma),
            'tipLocMunca' => $this->tipLocMunca,
            'judetLocMunca' => $this->judetLocMunca,
            'nivelStudii' => $this->nivelStudii,
        ];

        // The current state is set by REGES, never by the client, but the key has to
        // be there as an empty object ("{}"). An empty array ("[]") is rejected.
        $continut['stareCurenta'] = new \stdClass();

        if ($propunere) {
            // Proposal content needs a currency, and carries the all-zero id in
            // place of the employee reference: REGES resolves the employee from
            // referintaContract for proposal operations.
            $continut['moneda'] = 'RON';
            $continut['referintaSalariat'] = [
                '$type' => 'referinta',
                'id' => Uuid::NIL,
            ];
        }

        if ($this->localitateLocMunca) {
            $continut['localitateLocMunca'] = [
                'codSiruta' => $this->localitateLocMunca,
            ];
        }

        return Json::withoutNulls($continut);
    }
}
