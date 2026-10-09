<?php

declare(strict_types=1);

/*
 * Walks a contract through its life in the test registry, one step per run:
 *
 *     make run f="examples/contract.php add"        AdaugareContract for the employee from register-salariat.php
 *     make run f="examples/contract.php modify"     ModificareContract: raises the salary
 *     make run f="examples/contract.php terminate"  IncetareContract
 *
 * Append "bare" to "terminate" to send nothing but what the operation itself
 * needs, i.e. without the contract content.
 */

use Slash197\Reges\Data\ActiuneIncetare;
use Slash197\Reges\Data\ContinutContract;
use Slash197\Reges\Data\Cor;
use Slash197\Reges\Data\DocumentJustificativ;
use Slash197\Reges\Data\TimpMunca;
use Slash197\Reges\Message;
use Slash197\Reges\Operation;
use Slash197\Reges\Support\Dates;

/** @var Slash197\Reges\Reges $reges */
$reges = require __DIR__ . '/bootstrap.php';
require __DIR__ . '/live.php';

$step = $argv[1] ?? '';
$bare = ($argv[2] ?? '') === 'bare';

$salariatFile = dirname(__DIR__) . '/playground/salariat.json';
$contractFile = dirname(__DIR__) . '/playground/contract.json';

$load = static function (string $file, string $hint): array {
    if (!is_file($file)) {
        fwrite(STDERR, basename($file) . " is missing. {$hint}\n");
        exit(2);
    }

    return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
};

$salariat = $load($salariatFile, 'Run examples/register-salariat.php first.');
$today = Dates::date((new DateTimeImmutable('now', new DateTimeZone(Dates::TIMEZONE)))->format('Y-m-d'));

// The contract keeps the dates it was added with, whenever the later steps run.
$start = is_file($contractFile)
    ? Dates::date(json_decode((string) file_get_contents($contractFile), true)['start'])
    : $today;

$continut = static fn (int $salariu): ContinutContract => new ContinutContract(
    referintaSalariat: $salariat['id'],
    cor: new Cor(251201, 11),
    dataContract: $start->modify('-1 day'),
    dataInceputContract: $start,
    numarContract: 'TEST-' . substr($salariat['cnp'], -4),
    salariu: $salariu,
    timpMunca: new TimpMunca(
        norma: 'NormaIntreaga840',
        repartizare: 'OreDeZi',
        durata: 8,
        intervalTimp: 'OrePeZi',
        repartizareMunca: 'Zilnic',
        inceputInterval: $start->setTime(9, 0),
        sfarsitInterval: $start->setTime(17, 0),
    ),
    tipContract: 'ContractIndividualMunca',
    tipDurata: 'Nedeterminata',
    tipNorma: 'NormaIntreaga',
    tipLocMunca: 'Fix',
    judetLocMunca: 'MS',
    localitateLocMunca: 114328,
    nivelStudii: 'Superioare',
);

if ($step === 'add') {
    $result = sendAndAwait($reges, Message::contract(Operation::AdaugareContract, continut: $continut(5000)));

    if ($result?->isSuccess()) {
        file_put_contents($contractFile, pretty(['id' => $result->ref, 'start' => $start->format('Y-m-d')]) . "\n");
        echo "Saved the contract id to playground/contract.json\n";
    }

    exit;
}

$contract = $load($contractFile, 'Run the "add" step first.');

if ($step === 'modify') {
    $result = sendAndAwait($reges, Message::contract(Operation::ModificareContract, $contract['id'], $continut(5500)));

    if ($result?->isSuccess()) {
        file_put_contents($contractFile, pretty($contract + ['modificare' => $result->ref]) . "\n");
        echo "Saved the history entry id to playground/contract.json\n";
    }

    exit;
}

if ($step === 'terminate') {
    sendAndAwait($reges, Message::contract(
        Operation::IncetareContract,
        $contract['id'],
        $bare ? null : $continut(5500),
        new ActiuneIncetare($today->modify('+1 day'), 'Art55LitB', 'Acordul partilor'),
        new DocumentJustificativ('Decizie', 'D-1', $today),
    ));

    exit;
}

fwrite(STDERR, "Usage: make run f=\"examples/contract.php add|modify|terminate [bare]\"\n");
exit(2);
