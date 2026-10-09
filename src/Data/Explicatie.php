<?php

declare(strict_types=1);

namespace Slash197\Reges\Data;

/**
 * @internal
 */
final class Explicatie
{
    /**
     * A blank explanation is left out of the message instead of being sent empty.
     */
    public static function normalize(?string $explicatie): ?string
    {
        return $explicatie !== null && trim($explicatie) === '' ? null : $explicatie;
    }
}
