<?php

declare(strict_types=1);

namespace Slash197\Reges;

/**
 * An employer-defined bonus type (TipSporAngajator).
 */
final readonly class BonusType
{
    /**
     * @param array<mixed> $raw What REGES returned
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $raw = [],
    ) {
    }
}
