<?php

declare(strict_types=1);

namespace slash197\Reges\Data;

use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Json;

/**
 * Work authorisation details of an employee who is not a Romanian citizen.
 */
final readonly class DetaliiSalariatStrain
{
    public function __construct(
        public ?string $tipAutorizatie = null,
        public ?string $tipAutorizatieExceptie = null,
        public ?\DateTimeInterface $dataInceputAutorizatie = null,
        public ?\DateTimeInterface $dataSfarsitAutorizatie = null,
        public ?string $numarAutorizatie = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'tipAutorizatie' => $this->tipAutorizatie,
            'tipAutorizatieExceptie' => $this->tipAutorizatieExceptie,
            'dataInceputAutorizatie' => Dates::format($this->dataInceputAutorizatie),
            'dataSfarsitAutorizatie' => Dates::format($this->dataSfarsitAutorizatie),
            'numarAutorizatie' => $this->numarAutorizatie,
        ]);
    }
}
