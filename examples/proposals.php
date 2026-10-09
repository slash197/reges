<?php

declare(strict_types=1);

/*
 * Plays both sides of proposals between two registries: the one in
 * REGES_USERNAME (source) and the one in REGES_USERNAME_2 (destination).
 *
 *     make run f="examples/proposals.php detasare"           secondment: proposed, accepted, ended
 *     make run f="examples/proposals.php detasare-respingere" secondment: proposed, rejected by the destination
 *     make run f="examples/proposals.php detasare-radiere"   secondment: proposed, changed, withdrawn by the source
 *     make run f="examples/proposals.php detasare-actiuni"   secondment: accepted, then extended, changed and ended
 *                                                            through contract operations of the source
 *     make run f="examples/proposals.php mutare-radiere"     transfer: proposed, withdrawn by the source
 *     make run f="examples/proposals.php mutare-respingere"  transfer: proposed, rejected by the destination
 *     make run f="examples/proposals.php mutare-acceptare"   transfer: proposed, accepted by the destination
 *
 * Each flow registers its own made-up employee and contract, and keeps its
 * progress in playground/proposals-<flow>.json. The transfer flows cannot
 * share a contract: one that has been proposed for transfer stays marked as
 * moved even after the proposal is withdrawn, and takes no further proposal.
 */

use slash197\Reges\Data\ActiuneDetasare;
use slash197\Reges\Data\Cor;
use slash197\Reges\Data\DetaliiPropunereDetasare;
use slash197\Reges\Data\DetaliiPropunereMutare;
use slash197\Reges\Data\InfoSalariat;
use slash197\Reges\Message;
use slash197\Reges\Operation;
use slash197\Reges\Reges;
use slash197\Reges\Support\Dates;

/** @var Reges $sursa */
$sursa = require __DIR__ . '/bootstrap.php';
$destinatie = regesFor('_2');
require __DIR__ . '/live.php';

$flow = $argv[1] ?? '';
$flows = ['detasare', 'detasare-respingere', 'detasare-radiere', 'detasare-actiuni', 'mutare-radiere', 'mutare-respingere', 'mutare-acceptare'];
if (!in_array($flow, $flows, true)) {
    fwrite(STDERR, 'Usage: make run f="examples/proposals.php ' . implode('|', $flows) . "\"\n");
    exit(2);
}

$progress = new Progress($sursa, "proposals-{$flow}", skipOthers: true);
$state = &$progress->state;

$angajator = static function (Reges $reges): array {
    $detalii = $reges->profile()->raw['info']['detalii'] ?? [];

    return ['cui' => (string) ($detalii['cui'] ?? ''), 'nume' => (string) ($detalii['nume'] ?? '')];
};

$state['cnp'] ??= fakeCnp();
$state['start'] ??= today()->format('Y-m-d');
$state['sursa'] ??= $angajator($sursa);
$state['destinatie'] ??= $angajator($destinatie);
$progress->save();

$start = Dates::date($state['start']);
$numar = 'TEST-' . substr($state['cnp'], -4);

$info = new InfoSalariat(
    cnp: $state['cnp'],
    nume: 'TESTESCU',
    prenume: str_starts_with($flow, 'detasare') ? 'DAN' : 'VASILE',
    adresa: 'STR. EXEMPLU, NR. 4',
    tipActIdentitate: 'CarteIdentitate',
    taraDomiciliu: 'România',
    nationalitate: 'România',
);
// Reads the employee id through $progress: it is only known once the first step has run.
$continut = fn () => testContinut($progress->state['salariat'], $numar, $start, 5000);

$progress->step('salariat', fn () => Message::salariat(Operation::InregistrareSalariat, $info));
$progress->step('contract', fn () => Message::contract(Operation::AdaugareContract, continut: $continut()));

if (str_starts_with($flow, 'detasare')) {
    // The secondment starts with the contract. One that starts in the future
    // puts a future-dated entry in the contract's history; REGES then refuses
    // every operation recorded before that date, and cannot end the proposal.
    $from = $start;
    $until = $start->modify('+60 days');

    $detalii = fn (DateTimeImmutable $until) => new DetaliiPropunereDetasare(
        temeiDetasare: 'CodulMuncii',
        cuiAngajatorDestinatie: $state['destinatie']['cui'],
        numeAngajatorDestinatie: $state['destinatie']['nume'],
        nationalitateAngajatorDestinatie: 'România',
        cuiAngajatorSursa: $state['sursa']['cui'],
        dataPropunere: today(),
        numarPropunere: "PD-{$numar}",
        dataInceput: $from,
        dataSfarsit: $until,
    );

    $progress->step('propunere', fn () => Message::propunereDetasare(
        Operation::PropunereDetasareContract,
        referintaContract: $state['contract'],
        detalii: $detalii($until),
        continutContract: $continut(),
        infoSalariat: $info,
        noteSursa: 'Propunere de test',
    ));

    $acceptare = fn () => Message::propunereDetasare(
        Operation::AcceptarePropunereDetasareContract,
        $state['propunere'],
        $state['contract'],
        continutContract: $continut(),
        infoSalariat: $info,
        noteDestinatie: 'De acord',
    );

    if ($flow === 'detasare') {
        $progress->step('acceptare', $acceptare, $destinatie);
        $progress->step('incetare', fn () => Message::propunereDetasare(
            Operation::IncetarePropunereDetasareContract,
            $state['propunere'],
        ));
    }

    if ($flow === 'detasare-respingere') {
        $progress->step('respingere', fn () => Message::propunereDetasare(
            Operation::RespingerePropunereDetasareContract,
            $state['propunere'],
            noteDestinatie: 'Nu este cazul',
        ), $destinatie);
    }

    if ($flow === 'detasare-radiere') {
        $progress->step('modificare', fn () => Message::propunereDetasare(
            Operation::ModificarePropunereDetasareContract,
            $state['propunere'],
            $state['contract'],
            $detalii($until->modify('+30 days')),
            $continut(),
            $info,
        ), optional: true);
        $progress->step('radiere', fn () => Message::propunereDetasare(
            Operation::RadierePropunereDetasareContract,
            $state['propunere'],
        ));
    }

    if ($flow === 'detasare-actiuni') {
        $progress->step('acceptare', $acceptare, $destinatie);

        // Once accepted, the secondment is managed by the source employer on
        // its own contract, the one REGES marked as seconded. The contract
        // created at the destination is never in that state.
        $actiune = fn (DateTimeImmutable $until, ?DateTimeImmutable $incetare = null) => new ActiuneDetasare(
            temeiDetasare: 'CodulMuncii',
            angajatorCui: $state['destinatie']['cui'],
            angajatorNume: $state['destinatie']['nume'],
            cor: new Cor(251201, 11),
            dataInceput: $from,
            dataSfarsit: $until,
            nationalitate: 'România',
            dataIncetareDetasare: $incetare,
        );
        $operatie = fn (string $name, Operation $operation, ?ActiuneDetasare $actiune = null) => $progress->step(
            $name,
            fn () => Message::contract($operation, $state['contract'], actiune: $actiune),
            optional: true,
        );

        $operatie('prelungire', Operation::PrelungireDetasareContract, $actiune($until->modify('+30 days')));
        // REGES only takes a change that moves the end date further out.
        $operatie('modificare', Operation::ModificareDetasareContract, $actiune($until->modify('+40 days')));
        $operatie('corectie', Operation::CorectieDetasareContract, $actiune($until->modify('+45 days')));
        $operatie('incetare', Operation::IncetareDetasareContract, $actiune($until->modify('+45 days'), $start->modify('+10 days')));
        $operatie('corectieIncetare', Operation::CorectieIncetareDetasareContract, $actiune($until->modify('+45 days'), $start->modify('+12 days')));
        $operatie('anulareIncetare', Operation::AnulareIncetareDetasareContract);
        $operatie('anulare', Operation::AnulareDetasareContract);
    }
}

if (str_starts_with($flow, 'mutare-')) {
    $progress->step('propunere', fn () => Message::propunereMutare(
        Operation::PropunereMutareContract,
        referintaContract: $state['contract'],
        detalii: new DetaliiPropunereMutare(
            tipMutare: 'Transfer',
            tipTransfer: 'TransferDeIntreprindere',
            cuiAngajatorDestinatie: $state['destinatie']['cui'],
            numeAngajatorDestinatie: $state['destinatie']['nume'],
            nationalitateAngajatorDestinatie: 'România',
            cuiAngajatorSursa: $state['sursa']['cui'],
            dataPropunere: today(),
            numarPropunere: "PM-{$numar}",
            dataInceput: $start->modify('+30 days'),
        ),
        continutContract: $continut(),
        infoSalariat: $info,
        noteSursa: 'Propunere de test',
    ));

    match ($flow) {
        'mutare-radiere' => $progress->step('radiere', fn () => Message::propunereMutare(
            Operation::RadierePropunereMutareContract,
            $state['propunere'],
        )),
        'mutare-respingere' => $progress->step('respingere', fn () => Message::propunereMutare(
            Operation::RespingerePropunereMutareContract,
            $state['propunere'],
            noteDestinatie: 'Nu este cazul',
        ), $destinatie),
        'mutare-acceptare' => $progress->step('acceptare', fn () => Message::propunereMutare(
            Operation::AcceptarePropunereMutareContract,
            $state['propunere'],
            $state['contract'],
            continutContract: $continut(),
            infoSalariat: $info,
            noteDestinatie: 'De acord',
        ), $destinatie),
        default => null,
    };
}

// Whatever each party was told along the way.
foreach (['sursa' => $sursa, 'destinatie' => $destinatie] as $label => $reges) {
    while ($result = $reges->results()->read()) {
        echo "({$label} queue: {$result->operation} {$result->code}: {$result->description})\n";
        $reges->results()->commit();
    }
}

echo "Done.\n";
