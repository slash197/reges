<?php

declare(strict_types=1);

namespace slash197\Reges;

/**
 * The employer registry the credentials belong to.
 */
final readonly class Profile
{
    /**
     * @param string|null  $angajatorId REGES id of the employer
     * @param array<mixed> $raw         Everything REGES returned
     */
    public function __construct(
        public ?string $angajatorId,
        public array $raw,
    ) {
    }
}
