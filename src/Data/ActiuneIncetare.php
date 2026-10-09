<?php

declare(strict_types=1);

namespace Slash197\Reges\Data;

use Slash197\Reges\Support\Dates;
use Slash197\Reges\Support\Json;

final readonly class ActiuneIncetare implements Actiune
{
    /**
     * @param string|null $temeiLegal Code from the TemeiIncetare nomenclator
     */
    public function __construct(
        public ?\DateTimeInterface $dataIncetare = null,
        public ?string $temeiLegal = null,
        public ?string $explicatie = null,
    ) {
    }

    public function toArray(): array
    {
        return Json::withoutNulls([
            '$type' => 'actiuneIncetare',
            'dataIncetare' => Dates::format($this->dataIncetare),
            'temeiLegal' => $this->temeiLegal,
            'explicatie' => Explicatie::normalize($this->explicatie),
        ]);
    }
}
