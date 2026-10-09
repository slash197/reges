<?php

declare(strict_types=1);

namespace Slash197\Reges;

enum Environment: string
{
    case Test = 'test';
    case Production = 'production';

    public function apiUrl(): string
    {
        return match ($this) {
            self::Test => 'https://api.dev.inspectiamuncii.org',
            self::Production => 'https://api.inspectiamuncii.ro',
        };
    }

    public function tokenUrl(): string
    {
        return match ($this) {
            self::Test => 'https://sso.dev.inspectiamuncii.org/realms/API/protocol/openid-connect/token',
            // Derived from the test URL by analogy; not confirmed against production yet.
            // Override it through Config::$tokenUrl if it turns out to differ.
            self::Production => 'https://sso.inspectiamuncii.ro/realms/API/protocol/openid-connect/token',
        };
    }
}
