<?php

declare(strict_types=1);

/*
 * The corrections and cancellations around the main operations, each flow on
 * its own made-up employee and contract. Progress is kept in
 * playground/variants-<flow>.json.
 *
 *     make run f="examples/variants.php incetare"    employee changes; contract corrected, ended, end corrected and
 *                                                    cancelled, ended again, reactivated, reactivation cancelled
 *     make run f="examples/variants.php suspendare"  suspension changed, ended, end corrected, cancelled
 *     make run f="examples/variants.php istoric"     changes and a suspension added to the history of a contract
 *                                                    that started 90 days ago
 */

use slash197\Reges\Data\ActiuneIncetare;
use slash197\Reges\Data\ActiuneReactivare;
use slash197\Reges\Data\ActiuneSuspendare;
use slash197\Reges\Data\ContinutContract;
use slash197\Reges\Data\DocumentJustificativ;
use slash197\Reges\Data\InfoSalariat;
use slash197\Reges\Message;
use slash197\Reges\Operation;
use slash197\Reges\Support\Dates;

/** @var slash197\Reges\Reges $reges */
$reges = require __DIR__ . '/bootstrap.php';
require __DIR__ . '/live.php';

$flow = $argv[1] ?? '';
if (!in_array($flow, ['incetare', 'suspendare', 'istoric'], true)) {
    fwrite(STDERR, "Usage: make run f=\"examples/variants.php incetare|suspendare|istoric\"\n");
    exit(2);
}

$progress = new Progress($reges, "variants-{$flow}");
$state = &$progress->state;

$state['cnp'] ??= fakeCnp();
$state['start'] ??= today($flow === 'istoric' ? '-90 days' : '+0 days')->format('Y-m-d');
$progress->save();

$start = Dates::date($state['start']);
$numar = 'TEST-' . substr($state['cnp'], -4);

$info = static fn (mixed ...$changes) => new InfoSalariat(...$changes + [
    'cnp' => $state['cnp'],
    'nume' => 'TESTESCU',
    'prenume' => 'RADU',
    'adresa' => 'STR. EXEMPLU, NR. 5',
    'tipActIdentitate' => 'CarteIdentitate',
    'taraDomiciliu' => 'România',
    'nationalitate' => 'România',
]);

// Reads the employee id through $progress: it is only known once the first step has run.
$continut = function (int $salariu, ?DateTimeImmutable $dataConsemnare = null) use ($progress, $numar, $start): ContinutContract {
    $continut = testContinut($progress->state['salariat'], $numar, $start, $salariu);

    return $dataConsemnare === null ? $continut : new ContinutContract(...['dataConsemnare' => $dataConsemnare] + get_object_vars($continut));
};
$document = static fn (string $numar) => new DocumentJustificativ('Decizie', $numar, today());
$contract = fn (string $name, Operation $operation, mixed ...$parts) => $progress->step(
    $name,
    fn () => Message::contract($operation, $progress->state['contract'], ...$parts),
    needs: ['contract'],
    optional: true,
);

$progress->step('salariat', fn () => Message::salariat(Operation::InregistrareSalariat, $info()));
$progress->step('contract', fn () => Message::contract(Operation::AdaugareContract, continut: $continut(5000)));

if ($flow === 'incetare') {
    $progress->step('modificareSalariat', fn () => Message::salariat(
        Operation::ModificareSalariat,
        $info(adresa: 'STR. EXEMPLU, NR. 55'),
        $state['salariat'],
    ), optional: true);
    $progress->step('corectieSalariat', fn () => Message::salariat(
        Operation::CorectieSalariat,
        $info(adresa: 'STR. EXEMPLU, NR. 55', prenume: 'RADU-ION'),
        $state['salariat'],
    ), optional: true);

    $contract('corectieContract', Operation::CorectieContract, continut: $continut(5100));

    $incetare = static fn (string $day) => new ActiuneIncetare(today($day), 'Art55LitB', 'Acordul partilor');

    $contract('incetare', Operation::IncetareContract, actiune: $incetare('+1 day'), documentJustificativ: $document('I-1'));
    $contract('corectieIncetare', Operation::CorectieIncetareContract, actiune: $incetare('+2 days'), documentJustificativ: $document('I-2'));
    $contract('anulareIncetare', Operation::AnulareIncetareContract, documentJustificativ: $document('I-3'));
    $contract('incetareDinNou', Operation::IncetareContract, actiune: $incetare('+1 day'), documentJustificativ: $document('I-4'));
    $contract(
        'reactivare',
        Operation::ReactivareContract,
        actiune: new ActiuneReactivare(today('+2 days'), 'Reintegrare', 'Reintegrare in munca'),
        documentJustificativ: new DocumentJustificativ('HotarareJudecatoreasca', 'H-1', today()),
    );
    $contract(
        'anulareReactivare',
        Operation::AnulareReactivareContract,
        documentJustificativ: new DocumentJustificativ('HotarareJudecatoreasca', 'H-2', today()),
    );
}

if ($flow === 'suspendare') {
    $suspendare = static fn (int $until, ?int $incetare = null) => new ActiuneSuspendare(
        dataInceput: $start->modify('+1 day'),
        temeiLegal: 'Art54',
        dataSfarsit: $start->add(new DateInterval("P{$until}D")),
        dataIncetareSuspendare: $incetare === null ? null : $start->add(new DateInterval("P{$incetare}D")),
        explicatie: 'Acordul partilor',
    );

    $contract('suspendare', Operation::SuspendareContract, actiune: $suspendare(30), documentJustificativ: $document('S-1'));
    $contract('modificare', Operation::ModificareSuspendareContract, actiune: $suspendare(40), documentJustificativ: $document('S-2'));
    $contract('incetare', Operation::IncetareSuspendareContract, actiune: $suspendare(40, 10), documentJustificativ: $document('S-3'));
    $contract('corectieIncetare', Operation::CorectieIncetareSuspendareContract, actiune: $suspendare(40, 12), documentJustificativ: $document('S-4'));

    // Cancelling takes a contract that is suspended, which it no longer is after the suspension ended.
    $dinNou = new ActiuneSuspendare($start->modify('+20 days'), 'Art54', $start->modify('+35 days'), explicatie: 'Acordul partilor');
    $contract('suspendareDinNou', Operation::SuspendareContract, actiune: $dinNou, documentJustificativ: $document('S-5'));
    $contract('anulare', Operation::AnulareSuspendareContract, documentJustificativ: $document('S-6'));
}

if ($flow === 'istoric') {
    // A change made today, so that there is a "now" for the history to sit before.
    $contract('modificare', Operation::ModificareContract, continut: $continut(6000));

    // Each new entry goes after an existing one, named by $after, and takes effect on its own date.
    $istoric = fn (string $name, Operation $operation, string $after, mixed ...$parts) => $progress->step(
        $name,
        fn () => Message::contract($operation, $progress->state[$after], ...$parts),
        needs: [$after],
        optional: true,
    );

    $istoric('adaugareModificare', Operation::AdaugareModificareInIstoricContract, 'contract', continut: $continut(5200, $start->modify('+20 days')));
    $istoric(
        'adaugareModificareCuPropagare',
        Operation::AdaugareModificareInIstoricContractCuPropagare,
        'adaugareModificare',
        continut: $continut(5400, $start->modify('+40 days')),
    );
    $istoric(
        'corectieCuPropagare',
        Operation::CorectieIstoricContractCuPropagare,
        'adaugareModificare',
        continut: $continut(5300, $start->modify('+20 days')),
    );
    $istoric(
        'adaugareSuspendare',
        Operation::AdaugareSuspendareInIstoricContract,
        'adaugareModificareCuPropagare',
        actiune: new ActiuneSuspendare($start->modify('+50 days'), 'Art54', $start->modify('+55 days'), explicatie: 'Acordul partilor'),
    );
}

echo "Done.\n";
