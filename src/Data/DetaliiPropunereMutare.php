<?php

declare(strict_types=1);

namespace slash197\Reges\Data;

use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Json;

/**
 * The terms of a proposal to move a contract to another employer
 * (transfer, change of workplace or change of registry management).
 */
final readonly class DetaliiPropunereMutare
{
    /**
     * @param string|null $tipMutare                        Code from the TipMutare nomenclator
     * @param string|null $tipTransfer                      Code from the TipTransfer nomenclator, needed when tipMutare is "Transfer"
     * @param string|null $idAngajatorDestinatie            REGES id of the destination employer
     * @param string|null $nationalitateAngajatorDestinatie Country name, as in the nomenclator
     */
    public function __construct(
        public ?string $tipMutare = null,
        public ?string $cuiAngajatorDestinatie = null,
        public ?string $numeAngajatorDestinatie = null,
        public ?string $nationalitateAngajatorDestinatie = null,
        public ?string $cuiAngajatorSursa = null,
        public ?\DateTimeInterface $dataPropunere = null,
        public ?string $numarPropunere = null,
        public ?\DateTimeInterface $dataInceput = null,
        public ?string $tipTransfer = null,
        public ?string $idAngajatorDestinatie = null,
        public ?string $numeAngajatorSursa = null,
        public ?\DateTimeInterface $dataSfarsit = null,
        public ?\DateTimeInterface $dataIncetare = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'tipMutare' => $this->tipMutare,
            'tipTransfer' => $this->tipTransfer,
            'cuiAngajatorDestinatie' => Cui::normalize($this->cuiAngajatorDestinatie),
            'idAngajatorDestinatie' => $this->idAngajatorDestinatie
                ? ['id' => $this->idAngajatorDestinatie]
                : null,
            'numeAngajatorDestinatie' => $this->numeAngajatorDestinatie,
            'nationalitateAngajatorDestinatie' => $this->nationalitateAngajatorDestinatie
                ? ['nume' => $this->nationalitateAngajatorDestinatie]
                : null,
            'cuiAngajatorSursa' => Cui::normalize($this->cuiAngajatorSursa),
            'numeAngajatorSursa' => $this->numeAngajatorSursa,
            'dataPropunere' => Dates::format($this->dataPropunere),
            'numarPropunere' => $this->numarPropunere,
            'dataInceput' => Dates::format($this->dataInceput),
            'dataSfarsit' => Dates::format($this->dataSfarsit),
            'dataIncetare' => Dates::format($this->dataIncetare),
        ]);
    }
}
