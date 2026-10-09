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

use slash197\Reges\Exception\RegesException;

/** @var slash197\Reges\Reges $reges */
$reges = require dirname(__DIR__) . '/examples/bootstrap.php';

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
