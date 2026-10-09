<?php

declare(strict_types=1);

/*
 * Creates an employer-defined bonus type in the test registry, renames it and
 * deletes it again.
 *
 *     make run f=examples/bonus-type.php
 */

use slash197\Reges\Exception\ApiException;

/** @var slash197\Reges\Reges $reges */
$reges = require __DIR__ . '/bootstrap.php';

$names = static fn (): array => array_column($reges->nomenclators()->tipSporAngajator(), 'nume', 'id');
$name = 'Spor test ' . date('His');

try {
    echo 'Before: ', count($names()), " bonus types\n";

    $bonus = $reges->bonusTypes()->create($name);
    echo "Created {$bonus->id} \"{$bonus->name}\", listed: ", var_export(isset($names()[$bonus->id]), true), "\n";

    $bonus = $reges->bonusTypes()->update($bonus->id, "{$name} modificat");
    echo "Renamed to \"{$bonus->name}\", listed as \"", $names()[$bonus->id] ?? 'MISSING', "\"\n";

    $reges->bonusTypes()->delete($bonus->id, $bonus->name);
    echo 'Deleted, still listed: ', var_export(isset($names()[$bonus->id]), true), "\n";

    echo 'After: ', count($names()), " bonus types\n";
} catch (ApiException $exception) {
    echo "FAILED: {$exception->getMessage()}\n{$exception->body}\n";
    exit(1);
}
