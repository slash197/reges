<?php

declare(strict_types=1);

namespace Slash197\Reges\Support;

final class Json
{
    public static function encode(mixed $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * Decodes to arrays, except that empty JSON objects stay objects.
     *
     * json_decode($json, true) turns "{}" into an empty PHP array, which encodes
     * back as "[]". REGES rejects that for fields such as stareCurenta, so a
     * message that is stored and sent later has to survive the round trip.
     */
    public static function decodePreservingEmptyObjects(string $json): mixed
    {
        return self::preserveEmptyObjects(json_decode($json, false, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<mixed>|null
     */
    public static function decodeArray(string $json): ?array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    public static function withoutNulls(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => $value !== null);
    }

    private static function preserveEmptyObjects(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);

            return $properties === [] ? $value : array_map(self::preserveEmptyObjects(...), $properties);
        }

        if (is_array($value)) {
            return array_map(self::preserveEmptyObjects(...), $value);
        }

        return $value;
    }
}
