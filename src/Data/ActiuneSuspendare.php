<?php

declare(strict_types=1);

namespace slash197\Reges\Data;

use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Json;

final readonly class ActiuneSuspendare implements Actiune
{
    /**
     * @param string|null             $temeiLegal             Code from the TemeiSuspendare nomenclator
     * @param \DateTimeInterface|null $dataIncetareSuspendare Needed when ending a suspension
     */
    public function __construct(
        public ?\DateTimeInterface $dataInceput = null,
        public ?string $temeiLegal = null,
        public ?\DateTimeInterface $dataSfarsit = null,
        public ?\DateTimeInterface $dataIncetareSuspendare = null,
        public ?string $explicatie = null,
    ) {
    }

    public function toArray(): array
    {
        return Json::withoutNulls([
            '$type' => 'actiuneSuspendare',
            'dataInceput' => Dates::format($this->dataInceput),
            'dataSfarsit' => Dates::format($this->dataSfarsit),
            'dataIncetareSuspendare' => Dates::format($this->dataIncetareSuspendare),
            'temeiLegal' => $this->temeiLegal,
            'explicatie' => Explicatie::normalize($this->explicatie),
        ]);
    }
}
