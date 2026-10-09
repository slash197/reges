<?php

declare(strict_types=1);

namespace slash197\Reges\Data;

use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Json;

/**
 * A change to a secondment that the destination employer has accepted.
 *
 * These operations are sent by the source employer, on its own contract (the
 * one REGES marked as seconded), and describe the destination employer.
 * The contract REGES creates at the destination is never in that state.
 */
final readonly class ActiuneDetasare implements Actiune
{
    /**
     * @param string|null             $temeiDetasare        Code from the TemeiDetasare nomenclator
     * @param string|null             $angajatorCui         Fiscal code of the destination employer
     * @param string|null             $nationalitate        Country name of the destination employer, as in the nomenclator
     * @param \DateTimeInterface|null $dataSfarsit          A change or extension has to move this further out
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
