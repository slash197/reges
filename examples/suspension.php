<?php

declare(strict_types=1);

/*
 * Registers a made-up employee with a contract, suspends the contract and
 * ends the suspension. Progress is kept in playground/suspension.json, so a
 * run that stops at a failed step picks up there next time.
 *
 *     make run f=examples/suspension.php
 */

use slash197\Reges\Data\ActiuneSuspendare;
use slash197\Reges\Data\DocumentJustificativ;
use slash197\Reges\Data\InfoSalariat;
use slash197\Reges\Message;
use slash197\Reges\Operation;
use slash197\Reges\Support\Dates;

/** @var slash197\Reges\Reges $reges */
$reges = require __DIR__ . '/bootstrap.php';
require __DIR__ . '/live.php';

$file = dirname(__DIR__) . '/playground/suspension.json';
$state = is_file($file) ? json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];

$step = static function (string $name, callable $message) use ($reges, $file, &$state): void {
    if (isset($state[$name])) {
        echo "== {$name}: already done\n";

        return;
    }

    echo "== {$name}\n";
    $result = sendAndAwait($reges, $message());

    if (!$result?->isSuccess()) {
        exit(1);
    }

    $state[$name] = $result->ref;
    file_put_contents($file, pretty($state) . "\n");
};

$state['cnp'] ??= fakeCnp();
$state['start'] ??= today()->format('Y-m-d');
$start = Dates::date($state['start']);

$step('salariat', static fn () => Message::salariat(Operation::InregistrareSalariat, new InfoSalariat(
    cnp: $state['cnp'],
    nume: 'TESTESCU',
    prenume: 'ANDREI',
    adresa: 'STR. EXEMPLU, NR. 2',
    tipActIdentitate: 'CarteIdentitate',
    taraDomiciliu: 'România',
    nationalitate: 'România',
)));

$step('contract', static fn () => Message::contract(
    Operation::AdaugareContract,
    continut: testContinut($state['salariat'], 'TEST-' . substr($state['cnp'], -4), $start, 5000),
));

$suspendare = static fn (?DateTimeImmutable $incetare = null) => new ActiuneSuspendare(
    dataInceput: $start->modify('+1 day'),
    temeiLegal: 'Art54',
    dataSfarsit: $start->modify('+30 days'),
    dataIncetareSuspendare: $incetare,
    explicatie: 'Acordul partilor',
);

$step('suspendare', static fn () => Message::contract(
    Operation::SuspendareContract,
    $state['contract'],
    actiune: $suspendare(),
    documentJustificativ: new DocumentJustificativ('Decizie', 'S-1', $start),
));

$step('incetareSuspendare', static fn () => Message::contract(
    Operation::IncetareSuspendareContract,
    $state['contract'],
    actiune: $suspendare($start->modify('+10 days')),
    documentJustificativ: new DocumentJustificativ('Decizie', 'S-2', $start),
));

echo "Done.\n";
