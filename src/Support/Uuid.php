<?php

declare(strict_types=1);

namespace slash197\Reges\Support;

final class Uuid
{
    public const NIL = '00000000-0000-0000-0000-000000000000';

    public static function v4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}$/D', $value) === 1;
    }
}
