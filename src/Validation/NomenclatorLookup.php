<?php

declare(strict_types=1);

namespace Slash197\Reges\Validation;

/**
 * Lets validation check values against your local copy of the REGES
 * nomenclators. Without one, those checks are skipped.
 */
interface NomenclatorLookup
{
    /** Country names; checked for taraDomiciliu and nationalitate. */
    public const NATIONALITATE = 'nationalitate';

    /** Identity document type codes. */
    public const TIP_ACT_IDENTITATE = 'tipactidentitate';

    /** SIRUTA codes of localities. */
    public const LOCALITATE = 'localitate';

    /**
     * @param string $nomenclator One of the constants above
     */
    public function has(string $nomenclator, string $value): bool;
}
