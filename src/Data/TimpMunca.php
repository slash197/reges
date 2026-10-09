<?php

declare(strict_types=1);

namespace Slash197\Reges\Data;

use Slash197\Reges\Support\Dates;
use Slash197\Reges\Support\Json;

final readonly class TimpMunca
{
    /**
     * @param string|null $norma Either norm code set is accepted, see {@see Norma}
     */
    public function __construct(
        public ?string $norma = null,
        public ?string $repartizare = null,
        public int|float|null $durata = null,
        public ?string $intervalTimp = null,
        public ?string $tipTura = null,
        public ?string $observatiiTipTuraAlta = null,
        public ?string $repartizareMunca = null,
        public ?string $notaRepartizareMunca = null,
        public ?\DateTimeInterface $inceputInterval = null,
        public ?\DateTimeInterface $sfarsitInterval = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'durata' => $this->durata,
            'intervalTimp' => $this->intervalTimp,
            'norma' => Norma::timpMunca($this->norma),
            'repartizare' => $this->repartizare,
            'tipTura' => $this->tipTura,
            'observatiiTipTuraAlta' => $this->observatiiTipTuraAlta,
            'repartizareMunca' => $this->repartizareMunca,
            'notaRepartizareMunca' => $this->notaRepartizareMunca,
            'inceputInterval' => Dates::format($this->inceputInterval),
            'sfarsitInterval' => Dates::format($this->sfarsitInterval),
        ]);
    }
}
