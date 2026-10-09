<?php

declare(strict_types=1);

/*
 * Registers a made-up employee with a contract, changes the contract, then
 * corrects and strikes off that change in the contract's history, and finally
 * strikes off the contract and the employee. Progress is kept in
 * playground/history.json.
 *
 *     make run f=examples/history.php
 */

use slash197\Reges\Data\InfoSalariat;
use slash197\Reges\Message;
use slash197\Reges\Operation;
use slash197\Reges\Support\Dates;

/** @var slash197\Reges\Reges $reges */
$reges = require __DIR__ . '/bootstrap.php';
require __DIR__ . '/live.php';

$progress = new Progress($reges, 'history');
$state = &$progress->state;

$state['cnp'] ??= fakeCnp();
$state['start'] ??= today()->format('Y-m-d');
$start = Dates::date($state['start']);

$info = static fn (mixed ...$extra) => new InfoSalariat(...$extra + [
    'cnp' => $state['cnp'],
    'nume' => 'TESTESCU',
    'prenume' => 'MIHAI',
    'adresa' => 'STR. EXEMPLU, NR. 3',
    'tipActIdentitate' => 'CarteIdentitate',
    'taraDomiciliu' => 'România',
    'nationalitate' => 'România',
]);
// Reads the employee id through $progress: it is only known once the first step has run.
$continut = fn (int $salariu) => testContinut($progress->state['salariat'], 'TEST-' . substr($state['cnp'], -4), $start, $salariu);

$progress->step('salariat', fn () => Message::salariat(Operation::InregistrareSalariat, $info()));

$progress->step('contract', fn () => Message::contract(Operation::AdaugareContract, continut: $continut(5000)));

// The "ref" of this result is the history entry the next two steps refer to.
$progress->step('modificare', fn () => Message::contract(Operation::ModificareContract, $state['contract'], $continut(5500)));

$progress->step('corectieIstoric', fn () => Message::contract(Operation::CorectieIstoricContract, $state['modificare'], $continut(5600)));

$progress->step('radiereIstoric', fn () => Message::contract(
    Operation::RadiereIstoricContract,
    $state['modificare'],
    motivRadiere: 'Modificare inregistrata din eroare',
));

$progress->step('radiereContract', fn () => Message::contract(
    Operation::RadiereContract,
    $state['contract'],
    motivRadiere: 'Contract inregistrat din eroare',
));

$progress->step('radiereSalariat', fn () => Message::salariat(
    Operation::RadiereSalariat,
    $info(radiat: true, motivRadiere: 'Salariat inregistrat din eroare'),
    $state['salariat'],
));

echo "Done.\n";
