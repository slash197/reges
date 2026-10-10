<?php

declare(strict_types=1);

namespace slash197\Reges\Tests\Unit;

use slash197\Reges\Data\ActiuneDetasare;
use slash197\Reges\Data\ActiuneSuspendare;
use slash197\Reges\Data\ContinutContract;
use slash197\Reges\Data\DetaliiPropunereDetasare;
use slash197\Reges\Data\DetaliiPropunereMutare;
use slash197\Reges\Data\DocumentJustificativ;
use slash197\Reges\Data\InfoSalariat;
use slash197\Reges\Data\TimpMunca;
use slash197\Reges\Exception\ValidationException;
use slash197\Reges\Message;
use slash197\Reges\Operation;
use slash197\Reges\Support\Dates;
use slash197\Reges\Tests\Support\RegesTestCase;
use slash197\Reges\Validation\NomenclatorLookup;

final class ValidationTest extends RegesTestCase
{
    public function testEveryMissingFieldIsReportedAtOnce(): void
    {
        $errors = $this->errors(Message::contract(Operation::ModificareContract, continut: new ContinutContract(radiat: null)));

        self::assertSame([
            'referintaContract.id',
            'continut.referintaSalariat.id',
            'continut.cor.cod',
            'continut.cor.versiune',
            'continut.dataContract',
            'continut.dataInceputContract',
            'continut.numarContract',
            'continut.radiat',
            'continut.salariu',
            'continut.timpMunca',
            'continut.timpMunca.norma',
            'continut.timpMunca.repartizare',
            'continut.tipContract',
            'continut.tipDurata',
            'continut.tipNorma',
            'continut.timpMunca.intervalTimp',
            'continut.timpMunca.repartizareMunca',
            'continut.tipLocMunca',
            'continut.judetLocMunca',
            'continut.nivelStudii',
        ], array_keys($errors));
        self::assertSame(['required'], array_values(array_unique($errors)));
    }

    public function testExceptionMessageNamesTheFields(): void
    {
        $exception = new ValidationException(['referintaContract.id' => 'uuid', 'motivRadiere' => 'required']);

        self::assertSame('Invalid REGES message: referintaContract.id (uuid), motivRadiere (required).', $exception->getMessage());
        self::assertSame(['referintaContract.id', 'motivRadiere'], $exception->fields());
    }

    public function testCompleteMessagesPass(): void
    {
        self::assertSame([], $this->errors(Message::contract(Operation::AdaugareContract, continut: $this->continut())));
        self::assertSame([], $this->errors(Message::contract(Operation::ModificareContract, self::CONTRACT_ID, $this->continut())));
        self::assertSame([], $this->errors(Message::salariat(Operation::InregistrareSalariat, $this->info())));
        self::assertSame([], $this->errors(Message::contract(Operation::AnulareDetasareContract, self::CONTRACT_ID)));
    }

    public function testMissingContinutIsReportedAsOneField(): void
    {
        $errors = $this->errors(Message::contract(Operation::AdaugareContract));

        self::assertSame('required', $errors['continut']);
        self::assertArrayNotHasKey('referintaContract.id', $errors);
    }

    public function testReferencesMustBeUuids(): void
    {
        self::assertSame(
            ['referintaContract.id' => 'uuid'],
            $this->errors(Message::contract(Operation::AnulareTransferContract, 'contract-17')),
        );
        self::assertSame(
            ['referintaSalariat.id' => 'required'],
            $this->errors(Message::salariat(Operation::ModificareSalariat, $this->info())),
        );
        self::assertSame(
            ['referinta.id' => 'uuid'],
            $this->errors(Message::propunereDetasare(Operation::IncetarePropunereDetasareContract, 'x')),
        );
    }

    /**
     * REGES asks for more detail on any contract recorded from 1 April 2025,
     * and answers "Repartizare timp muncă lipsă!" and the like without it.
     */
    public function testContractsRecordedFromApril2025NeedTheExtendedContent(): void
    {
        $bare = [
            'timpMunca' => new TimpMunca('NormaIntreaga840', 'OreDeZi'),
            'tipLocMunca' => null,
            'judetLocMunca' => null,
            'nivelStudii' => null,
        ];
        $before = $this->continut(...$bare, dataConsemnare: Dates::date('2025-03-31'));
        $after = $this->continut(...$bare, dataConsemnare: Dates::date('2025-04-01'));

        self::assertSame([], $this->errors(Message::contract(Operation::AdaugareContract, continut: $before)));
        self::assertSame(
            [
                'continut.timpMunca.intervalTimp' => 'required',
                'continut.timpMunca.repartizareMunca' => 'required',
                'continut.tipLocMunca' => 'required',
                'continut.judetLocMunca' => 'required',
                'continut.nivelStudii' => 'required',
            ],
            $this->errors(Message::contract(Operation::AdaugareContract, continut: $after)),
        );
    }

    public function testExtendedContentDependsOnTheScheduleAndWorkplace(): void
    {
        $timpMunca = static fn (string $repartizareMunca) => new TimpMunca(
            'NormaIntreaga840',
            'OreDeZi',
            intervalTimp: 'OrePeZi',
            repartizareMunca: $repartizareMunca,
        );
        $errors = fn (mixed ...$overrides) => array_keys($this->errors(
            Message::contract(Operation::AdaugareContract, continut: $this->continut(...$overrides)),
        ));

        self::assertSame(
            ['continut.timpMunca.inceputInterval', 'continut.timpMunca.sfarsitInterval'],
            $errors(timpMunca: $timpMunca('Zilnic')),
        );
        self::assertSame(['continut.timpMunca.tipTura'], $errors(timpMunca: $timpMunca('Schimburi')));
        self::assertSame(['continut.localitateLocMunca.codSiruta'], $errors(tipLocMunca: 'Fix'));
        self::assertSame([], $errors(tipLocMunca: 'Fix', localitateLocMunca: 54984));
    }

    public function testExtendedContentIsNotCheckedOnProposals(): void
    {
        $errors = $this->errors(Message::propunereMutare(
            Operation::AcceptarePropunereMutareContract,
            self::PROPUNERE_ID,
            self::CONTRACT_ID,
            continutContract: $this->continut(nivelStudii: null, tipLocMunca: null, judetLocMunca: null),
            infoSalariat: $this->info(),
        ));

        self::assertSame([], $errors);
    }

    public function testSuspensionNeedsItsActionAndDocument(): void
    {
        $errors = $this->errors(Message::contract(Operation::IncetareSuspendareContract, self::CONTRACT_ID));
        self::assertSame([
            'documentJustificativ',
            'documentJustificativ.tipDocumentJustificativ',
            'documentJustificativ.numarDocumentJustificativ',
            'documentJustificativ.dataDocumentJustificativ',
            'actiune',
            'actiune.dataInceput',
            'actiune.temeiLegal',
            'actiune.dataIncetareSuspendare',
        ], array_keys($errors));

        $errors = $this->errors(Message::contract(
            Operation::IncetareSuspendareContract,
            self::CONTRACT_ID,
            actiune: new ActiuneSuspendare(Dates::date('2026-04-01'), 'Art51Alin1LitA'),
            documentJustificativ: new DocumentJustificativ('Cerere', 'C-4', Dates::date('2026-03-09')),
        ));
        self::assertSame(['actiune.dataIncetareSuspendare' => 'required'], $errors);
    }

    public function testEndingASecondmentNeedsTheEndDate(): void
    {
        $errors = $this->errors(Message::contract(
            Operation::IncetareDetasareContract,
            self::CONTRACT_ID,
            actiune: new ActiuneDetasare(temeiDetasare: 'Transnationala', angajatorCui: '1', angajatorNume: 'SAP AG'),
        ));

        self::assertSame([
            'actiune.cor.cod',
            'actiune.cor.versiune',
            'actiune.dataInceput',
            'actiune.dataSfarsit',
            'actiune.nationalitate.nume',
            'actiune.dataIncetareDetasare',
        ], array_keys($errors));
    }

    public function testRadiereNeedsAReason(): void
    {
        self::assertSame(
            ['motivRadiere' => 'required'],
            $this->errors(Message::contract(Operation::RadiereIstoricContract, self::CONTRACT_ID, motivRadiere: ' ')),
        );
    }

    public function testTransferTypeIsRequiredOnlyForTransfers(): void
    {
        $detalii = fn (string $tipMutare) => new DetaliiPropunereMutare(
            tipMutare: $tipMutare,
            cuiAngajatorDestinatie: '10000002',
            numeAngajatorDestinatie: 'DESTINATIE SRL',
            nationalitateAngajatorDestinatie: 'ROMÂNIA',
            cuiAngajatorSursa: '10000001',
            dataPropunere: Dates::date('2026-03-05'),
            numarPropunere: 'PM-1',
            dataInceput: Dates::date('2026-04-01'),
        );
        $message = fn (string $tipMutare) => Message::propunereMutare(
            Operation::PropunereMutareContract,
            referintaContract: self::CONTRACT_ID,
            detalii: $detalii($tipMutare),
            continutContract: $this->continut(),
            infoSalariat: $this->info(),
        );

        self::assertSame(['tipTransfer' => 'required'], $this->errors($message('Transfer')));
        self::assertSame([], $this->errors($message('SchimbareLocMunca')));
    }

    public function testSecondmentProposalNeedsItsTermsContentAndEmployee(): void
    {
        $errors = $this->errors(Message::propunereDetasare(
            Operation::PropunereDetasareContract,
            detalii: new DetaliiPropunereDetasare(temeiDetasare: 'Transnationala'),
        ));

        self::assertArrayHasKey('referintaContract.id', $errors);
        self::assertArrayHasKey('dataSfarsit', $errors);
        self::assertArrayHasKey('cuiAngajatorDestinatie', $errors);
        self::assertArrayHasKey('continutContract', $errors);
        self::assertArrayHasKey('infoSalariat', $errors);
        self::assertArrayHasKey('infoSalariat.cnp', $errors);
        self::assertArrayNotHasKey('temeiDetasare', $errors);
        self::assertArrayNotHasKey('referinta.id', $errors);
    }

    public function testEmployeeTextFieldsHaveALengthLimit(): void
    {
        $errors = $this->errors(Message::salariat(
            Operation::InregistrareSalariat,
            $this->info(adresa: str_repeat('ă', 257), nume: str_repeat('ă', 256), tipActIdentitate: str_repeat('x', 129)),
        ));

        self::assertSame(['info.adresa' => 'max:256', 'info.tipActIdentitate' => 'max:128'], $errors);
    }

    public function testNomenclatorValuesAreCheckedOnlyWithALookup(): void
    {
        $info = new InfoSalariat(
            cnp: '1800612015459',
            nume: 'POPESCU',
            prenume: 'ION',
            adresa: 'STR. SALARIATULUI, NR. 1',
            tipActIdentitate: 'Buletin',
            taraDomiciliu: 'ROMÂNIA',
            nationalitate: 'ATLANTIDA',
            localitate: 999,
        );
        $message = Message::salariat(Operation::InregistrareSalariat, $info);

        self::assertSame([], $this->errors($message));

        $lookup = new class () implements NomenclatorLookup {
            public function has(string $nomenclator, string $value): bool
            {
                return match ($nomenclator) {
                    self::NATIONALITATE => $value === 'ROMÂNIA',
                    self::TIP_ACT_IDENTITATE => $value === 'CarteIdentitate',
                    self::LOCALITATE => $value === '179141',
                    default => true,
                };
            }
        };

        self::assertSame([
            'info.tipActIdentitate' => 'in:tipactidentitate',
            'info.nationalitate.nume' => 'in:nationalitate',
            'info.localitate.codSiruta' => 'in:localitate',
        ], $this->errors($message, $lookup));
    }

    public function testSessionIdMustBeAUuid(): void
    {
        $message = Message::contract(Operation::AnulareTransferContract, self::CONTRACT_ID)->withSessionId('session-1');

        self::assertSame(['sessionId' => 'uuid'], $this->errors($message));
    }

    /**
     * @return array<string, string>
     */
    private function errors(Message $message, ?NomenclatorLookup $lookup = null): array
    {
        try {
            $this->regesWithoutToken(lookup: $lookup)->envelope($message);
        } catch (ValidationException $exception) {
            return $exception->errors;
        }

        return [];
    }
}
