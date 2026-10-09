<?php

declare(strict_types=1);

/*
 * Registers a made-up employee in the test registry and waits for the result.
 *
 *     make run f=examples/register-salariat.php
 */

use slash197\Reges\Data\InfoSalariat;
use slash197\Reges\Message;
use slash197\Reges\Operation;
use slash197\Reges\Support\Dates;

/** @var slash197\Reges\Reges $reges */
$reges = require __DIR__ . '/bootstrap.php';
require __DIR__ . '/live.php';

$cnp = fakeCnp();

$result = sendAndAwait($reges, Message::salariat(Operation::InregistrareSalariat, new InfoSalariat(
    cnp: $cnp,
    nume: 'TESTESCU',
    prenume: 'ION',
    adresa: 'STR. EXEMPLU, NR. 1',
    tipActIdentitate: 'CarteIdentitate',
    taraDomiciliu: 'România',
    nationalitate: 'România',
    dataNastere: Dates::date('1980-06-12'),
)));

if ($result?->isSuccess()) {
    file_put_contents(dirname(__DIR__) . '/playground/salariat.json', pretty(['cnp' => $cnp, 'id' => $result->ref]) . "\n");
    echo "Saved the employee id to playground/salariat.json\n";
}
