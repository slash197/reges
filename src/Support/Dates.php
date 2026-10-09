<?php

declare(strict_types=1);

namespace Slash197\Reges\Support;

final class Dates
{
    public const TIMEZONE = 'Europe/Bucharest';

    /**
     * REGES expects ISO 8601 date-times expressed in Romanian local time.
     */
    public static function format(?\DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new \DateTimeZone(self::TIMEZONE))
            ->format(\DATE_ATOM);
    }

    /**
     * Midnight Romanian time on the given calendar day ("2025-01-31"), for the
     * many REGES fields that are dates carried in a date-time format.
     */
    public static function date(string $day): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day, new \DateTimeZone(self::TIMEZONE));

        if ($date === false) {
            throw new \InvalidArgumentException("Expected a date as YYYY-MM-DD, got \"{$day}\".");
        }

        return $date;
    }
}
