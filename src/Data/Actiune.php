<?php

declare(strict_types=1);

namespace slash197\Reges\Data;

/**
 * Something that happens to an existing contract: termination, suspension,
 * reactivation or secondment.
 */
interface Actiune
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
