<?php

declare(strict_types=1);

namespace Slash197\Reges;

final readonly class Config
{
    public string $apiUrl;
    public string $tokenUrl;
    public string $clientApplication;
    public string $clientVersion;

    /**
     * @param string      $clientId          OAuth client id issued by REGES ("reges-api" on the test environment)
     * @param string      $clientApplication Name of the software sending the messages, reported in every header
     * @param string      $clientVersion     Version of that software
     * @param string|null $user              Default human user reported in the header; can be set per message instead
     * @param string|null $apiUrl            Overrides the environment's API host
     * @param string|null $tokenUrl          Overrides the environment's OAuth token endpoint
     */
    public function __construct(
        public Environment $environment,
        public string $clientId,
        #[\SensitiveParameter]
        public string $clientSecret,
        string $clientApplication,
        string $clientVersion,
        public ?string $user = null,
        ?string $apiUrl = null,
        ?string $tokenUrl = null,
    ) {
        $this->clientApplication = trim($clientApplication);
        $this->clientVersion = trim($clientVersion);

        if ($this->clientApplication === '' || $this->clientVersion === '') {
            throw new \InvalidArgumentException('REGES needs a non-empty clientApplication and clientVersion.');
        }

        $this->apiUrl = rtrim($apiUrl ?? $environment->apiUrl(), '/');
        $this->tokenUrl = $tokenUrl ?? $environment->tokenUrl();
    }
}
