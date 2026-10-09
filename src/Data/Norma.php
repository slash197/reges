<?php

declare(strict_types=1);

namespace Slash197\Reges\Data;

/**
 * REGES describes the work norm twice, with two different code sets:
 * continut.tipNorma is coarse (NormaIntreaga, TimpPartial, NormaOUG132) while
 * continut.timpMunca.norma is detailed (NormaIntreaga630, NormaIntreaga840,
 * NormaIntreagaLegiSpeciale, TimpOUG132, TimpPartial). A code from one set is
 * rejected in the other field, so both are converted on the way out.
 */
final class Norma
{
    public static function tipNorma(?string $norma): ?string
    {
        if ($norma === null) {
            return null;
        }

        if (in_array($norma, ['NormaIntreaga', 'TimpPartial', 'NormaOUG132'], true)) {
            return $norma;
        }

        if (str_starts_with($norma, 'NormaIntreaga')) {
            return 'NormaIntreaga';
        }

        if ($norma === 'TimpOUG132') {
            return 'NormaOUG132';
        }

        return $norma;
    }

    public static function timpMunca(?string $norma): ?string
    {
        if ($norma === null) {
            return null;
        }

        if (in_array($norma, ['NormaIntreaga630', 'NormaIntreaga840', 'NormaIntreagaLegiSpeciale', 'TimpOUG132', 'TimpPartial'], true)) {
            return $norma;
        }

        if ($norma === 'NormaIntreaga') {
            // The generic value says nothing about the schedule; 8h/day, 40h/week is the common case.
            return 'NormaIntreaga840';
        }

        if ($norma === 'NormaOUG132') {
            return 'TimpOUG132';
        }

        return $norma;
    }
}
