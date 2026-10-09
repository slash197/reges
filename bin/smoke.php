#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Read-only check against the REGES test environment: authenticates, fetches
 * the employer profile and one nomenclator, and peeks at the result queue
 * without committing. Nothing is written to the registry.
 *
 * Needs REGES_CLIENT_ID, REGES_CLIENT_SECRET, REGES_USERNAME and REGES_PASSWORD.
 */

use GuzzleHttp\Client;
use Slash197\Reges\Config;
use Slash197\Reges\Credentials;
use Slash197\Reges\Environment;
use Slash197\Reges\Exception\RegesException;
use Slash197\Reges\Reges;

require dirname(__DIR__) . '/vendor/autoload.php';

$env = static fn (string $name): string => trim((string) getenv($name), " \"'");

foreach (['REGES_CLIENT_ID', 'REGES_CLIENT_SECRET', 'REGES_USERNAME', 'REGES_PASSWORD'] as $name) {
    if ($env($name) === '') {
        fwrite(STDERR, "{$name} is not set. Copy .env.example to .env and fill it in.\n");
        exit(2);
    }
}

$reges = new Reges(
    new Config(Environment::Test, $env('REGES_CLIENT_ID'), $env('REGES_CLIENT_SECRET'), 'slash197/reges smoke test', '0'),
    new Credentials($env('REGES_USERNAME'), $env('REGES_PASSWORD')),
    new Client(['timeout' => 30, 'http_errors' => false]),
);

$step = static function (string $label, callable $call): void {
    try {
        echo str_pad($label, 22), $call(), "\n";
    } catch (RegesException $exception) {
        echo str_pad($label, 22), 'FAILED: ', $exception->getMessage(), "\n";
        exit(1);
    }
};

$step('Authentication', static function () use ($reges): string {
    $token = $reges->authenticate();

    return 'ok, token expires ' . ($token->expiresAt?->format(DATE_ATOM) ?? 'at an unknown time');
});

$step('Profile', static function () use ($reges): string {
    $profile = $reges->profile();

    return 'ok, employer id ' . ($profile->angajatorId !== null ? 'present' : 'MISSING')
        . ', keys: ' . implode(', ', array_keys($profile->raw));
});

$step('Nomenclator', static function () use ($reges): string {
    return 'ok, NivelStudii has ' . count($reges->nomenclators()->get('NivelStudii')) . ' entries';
});

$step('Employer bonus types', static function () use ($reges): string {
    return 'ok, ' . count($reges->nomenclators()->tipSporAngajator()) . ' defined';
});

$step('Result queue (peek)', static function () use ($reges): string {
    $result = $reges->results()->read();

    return $result === null
        ? 'ok, empty'
        : "ok, next is {$result->operation} => {$result->code} (not committed)";
});
