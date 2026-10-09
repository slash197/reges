<?php

declare(strict_types=1);

namespace slash197\Reges;

/**
 * The API key pair generated in the REGES employer application
 * (Setari -> Acces -> Chei API). One pair identifies one employer registry.
 */
final readonly class Credentials
{
    public string $username;
    public string $password;

    public function __construct(
        string $username,
        #[\SensitiveParameter]
        string $password,
    ) {
        $this->username = trim($username);
        $this->password = trim($password);
    }
}
