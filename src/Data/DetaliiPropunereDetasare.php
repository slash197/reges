<?php

declare(strict_types=1);

namespace Slash197\Reges\Data;

use Slash197\Reges\Support\Dates;
use Slash197\Reges\Support\Json;

/**
 * The terms of a proposal to second an employee to another employer.
 */
final readonly class DetaliiPropunereDetasare
{
    /**
     * @param string|null $temeiDetasare                    Code from the TemeiDetasare nomenclator
     * @param string|null $nationalitateAngajatorDestinatie Country name, as in the nomenclator
     */
    public function __construct(
        public ?string $temeiDetasare = null,
        public ?string $cuiAngajatorDestinatie = null,
        public ?string $numeAngajatorDestinatie = null,
        public ?string $nationalitateAngajatorDestinatie = null,
        public ?string $cuiAngajatorSursa = null,
        public ?\DateTimeInterface $dataPropunere = null,
        public ?string $numarPropunere = null,
        public ?\DateTimeInterface $dataInceput = null,
        public ?\DateTimeInterface $dataSfarsit = null,
        public ?string $numeAngajatorSursa = null,
        public ?\DateTimeInterface $dataIncetare = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'temeiDetasare' => $this->temeiDetasare,
            'cuiAngajatorDestinatie' => Cui::normalize($this->cuiAngajatorDestinatie),
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
