<?php

declare(strict_types=1);

namespace Slash197\Reges\Data;

/**
 * An occupation from the COR nomenclator, identified by its code and the
 * nomenclator version that code belongs to.
 */
final readonly class Cor
{
    public function __construct(
        public int $cod,
        public int $versiune,
    ) {
    }

    /**
     * @return array{cod: int, versiune: int}
     */
    public function toArray(): array
    {
        return [
            'cod' => $this->cod,
            'versiune' => $this->versiune,
        ];
    }
}
