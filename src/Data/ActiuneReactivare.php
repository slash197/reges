<?php

declare(strict_types=1);

namespace Slash197\Reges\Data;

use Slash197\Reges\Support\Dates;
use Slash197\Reges\Support\Json;

final readonly class ActiuneReactivare implements Actiune
{
    /**
     * @param string|null $temeiLegal Code from the TemeiReactivare nomenclator
     */
    public function __construct(
        public ?\DateTimeInterface $dataReactivare = null,
        public ?string $temeiLegal = null,
        public ?string $explicatie = null,
    ) {
    }

    public function toArray(): array
    {
        return Json::withoutNulls([
            '$type' => 'actiuneReactivare',
            'dataReactivare' => Dates::format($this->dataReactivare),
            'temeiLegal' => $this->temeiLegal,
            'explicatie' => Explicatie::normalize($this->explicatie),
        ]);
    }
}
