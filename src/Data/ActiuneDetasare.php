<?php

declare(strict_types=1);

namespace slash197\Reges\Data;

use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Json;

final readonly class ActiuneDetasare implements Actiune
{
    /**
     * @param string|null             $temeiDetasare        Code from the TemeiDetasare nomenclator
     * @param string|null             $nationalitate        Country name of the host employer, as in the nomenclator
     * @param \DateTimeInterface|null $dataIncetareDetasare Needed when ending a secondment
     */
    public function __construct(
        public ?string $temeiDetasare = null,
        public ?string $angajatorCui = null,
        public ?string $angajatorNume = null,
        public ?Cor $cor = null,
        public ?\DateTimeInterface $dataInceput = null,
        public ?\DateTimeInterface $dataSfarsit = null,
        public ?string $nationalitate = null,
        public ?\DateTimeInterface $dataIncetareDetasare = null,
    ) {
    }

    public function toArray(): array
    {
        return Json::withoutNulls([
            '$type' => 'actiuneDetasare',
            'temeiDetasare' => $this->temeiDetasare,
            'angajatorCui' => $this->angajatorCui,
            'angajatorNume' => $this->angajatorNume,
            'cor' => $this->cor?->toArray(),
            'dataInceput' => Dates::format($this->dataInceput),
            'dataSfarsit' => Dates::format($this->dataSfarsit),
            'dataIncetareDetasare' => Dates::format($this->dataIncetareDetasare),
            'nationalitate' => $this->nationalitate ? ['nume' => $this->nationalitate] : null,
        ]);
    }
}
