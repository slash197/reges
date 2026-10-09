<?php

declare(strict_types=1);

namespace slash197\Reges\Data;

/**
 * @internal
 */
final class Cui
{
    /**
     * REGES wants the bare fiscal code: "RO 13373052" becomes "13373052".
     */
    public static function normalize(?string $cui): ?string
    {
        if ($cui === null) {
            return null;
        }

        $normalized = strtoupper(trim($cui));
        if (str_starts_with($normalized, 'RO')) {
            $normalized = substr($normalized, 2);
        }

        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }
}
