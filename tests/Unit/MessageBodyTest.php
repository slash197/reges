<?php

declare(strict_types=1);

namespace slash197\Reges\Tests\Unit;

use slash197\Reges\Data\ActiuneIncetare;
use slash197\Reges\Data\DetaliiPropunereMutare;
use slash197\Reges\Data\DetaliiSalariatStrain;
use slash197\Reges\Data\DocumentJustificativ;
use slash197\Reges\Data\InfoSalariat;
use slash197\Reges\Data\Norma;
use slash197\Reges\Data\SporSalariu;
use slash197\Reges\Data\TimpMunca;
use slash197\Reges\Message;
use slash197\Reges\Operation;
use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Uuid;
use slash197\Reges\Tests\Support\RegesTestCase;

final class MessageBodyTest extends RegesTestCase
{
    public function testDataConsemnareIsTheGivenDateOnlyWhenAddingAContract(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $continut = $this->continut(dataConsemnare: Dates::date('2026-02-25'));

        $adaugare = Message::contract(Operation::AdaugareContract, continut: $continut)->body($now);
        self::assertSame('2026-02-25T02:00:00+02:00', $adaugare['continut']['dataConsemnare']);

        foreach ([Operation::ModificareContract, Operation::CorectieContract, Operation::CorectieIstoricContract] as $operation) {
            $body = Message::contract($operation, self::CONTRACT_ID, $continut)->body($now);
            self::assertSame(self::NOW, $body['continut']['dataConsemnare'], $operation->value);
        }
    }

    public function testDataConsemnareDefaultsToTheContractStartWhenAddingAContract(): void
    {
        $body = Message::contract(Operation::AdaugareContract, continut: $this->continut())->body(new \DateTimeImmutable(self::NOW));

        self::assertSame($body['continut']['dataInceputContract'], $body['continut']['dataConsemnare']);
    }

    public function testDatesAreSentInRomanianTime(): void
    {
        self::assertSame('2026-01-15T14:00:00+02:00', Dates::format(new \DateTimeImmutable('2026-01-15T12:00:00Z')));
        self::assertSame('2026-07-15T15:00:00+03:00', Dates::format(new \DateTime('2026-07-15T12:00:00Z')));
        self::assertNull(Dates::format(null));
    }

    /**
     * REGES records the UTC date of what it is sent: a contract dated midnight
     * Romanian time on the 8th was registered as dated the 7th.
     */
    public function testCalendarDaysKeepTheirDateInUtc(): void
    {
        foreach (['2026-01-15' => '2026-01-15T02:00:00+02:00', '2026-07-15' => '2026-07-15T03:00:00+03:00'] as $day => $sent) {
            self::assertSame($sent, Dates::format(Dates::date($day)));
            self::assertSame($day, (new \DateTimeImmutable($sent))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d'));
        }

        $this->expectException(\InvalidArgumentException::class);
        Dates::date('15.07.2026');
    }

    public function testStareCurentaIsAlwaysAnEmptyObject(): void
    {
        $body = Message::contract(Operation::AdaugareContract, continut: $this->continut())->body(new \DateTimeImmutable(self::NOW));

        self::assertEquals(new \stdClass(), $body['continut']['stareCurenta']);
        self::assertStringContainsString('"stareCurenta":{}', json_encode($body, \JSON_THROW_ON_ERROR));
    }

    public function testProposalContentCarriesCurrencyAndThePlaceholderEmployeeReference(): void
    {
        $now = new \DateTimeImmutable(self::NOW);

        $mutare = Message::propunereMutare(Operation::PropunereMutareContract, continutContract: $this->continut())->body($now);
        $detasare = Message::propunereDetasare(Operation::AcceptarePropunereDetasareContract, continutContract: $this->continut())->body($now);
        $contract = Message::contract(Operation::AdaugareContract, continut: $this->continut())->body($now);

        foreach ([$mutare, $detasare] as $body) {
            self::assertSame('RON', $body['continutContract']['moneda']);
            self::assertSame(['$type' => 'referinta', 'id' => Uuid::NIL], $body['continutContract']['referintaSalariat']);
            self::assertSame(self::NOW, $body['continutContract']['dataConsemnare']);
        }

        self::assertArrayNotHasKey('moneda', $contract['continut']);
        self::assertSame(self::SALARIAT_ID, $contract['continut']['referintaSalariat']['id']);
    }

    public function testPartsAnOperationDoesNotUseAreLeftOut(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $actiune = new ActiuneIncetare(Dates::date('2026-03-31'), 'Art55LitB');
        $document = new DocumentJustificativ('Decizie', 'D-17', Dates::date('2026-03-09'));

        $anulare = Message::contract(Operation::AnulareIncetareContract, self::CONTRACT_ID, actiune: $actiune, documentJustificativ: $document, motivRadiere: 'x')->body($now);
        self::assertSame(['referintaContract', 'documentJustificativ'], array_keys($anulare));

        $radiere = Message::contract(Operation::RadiereIstoricContract, self::CONTRACT_ID, actiune: $actiune, documentJustificativ: $document, motivRadiere: 'x')->body($now);
        self::assertSame(['referintaContract', 'motivRadiere'], array_keys($radiere));

        $respingere = Message::propunereMutare(
            Operation::RespingerePropunereMutareContract,
            self::PROPUNERE_ID,
            self::CONTRACT_ID,
            new DetaliiPropunereMutare(tipMutare: 'Transfer'),
            $this->continut(),
            $this->info(),
            'sursa',
            'destinatie',
        )->body($now);
        self::assertSame(['referinta' => ['id' => self::PROPUNERE_ID], 'noteDestinatie' => 'destinatie'], $respingere);
    }

    public function testBlankMotivRadiereIsLeftOut(): void
    {
        $body = Message::contract(Operation::RadiereContract, self::CONTRACT_ID, motivRadiere: '   ')->body(new \DateTimeImmutable(self::NOW));

        self::assertArrayNotHasKey('motivRadiere', $body);
    }

    /**
     * tipNorma and timpMunca.norma take different code sets for the same thing.
     */
    public function testNormIsConvertedToTheCodeSetOfEachField(): void
    {
        self::assertSame('NormaIntreaga', Norma::tipNorma('NormaIntreaga840'));
        self::assertSame('NormaIntreaga', Norma::tipNorma('NormaIntreaga630'));
        self::assertSame('NormaIntreaga', Norma::tipNorma('NormaIntreagaLegiSpeciale'));
        self::assertSame('NormaOUG132', Norma::tipNorma('TimpOUG132'));
        self::assertSame('TimpPartial', Norma::tipNorma('TimpPartial'));
        self::assertSame('Altceva', Norma::tipNorma('Altceva'));
        self::assertNull(Norma::tipNorma(null));

        self::assertSame('NormaIntreaga840', Norma::timpMunca('NormaIntreaga'));
        self::assertSame('NormaIntreaga630', Norma::timpMunca('NormaIntreaga630'));
        self::assertSame('TimpOUG132', Norma::timpMunca('NormaOUG132'));
        self::assertSame('TimpPartial', Norma::timpMunca('TimpPartial'));
        self::assertNull(Norma::timpMunca(null));

        $continut = $this->continut(tipNorma: 'NormaIntreaga630', timpMunca: new TimpMunca('NormaOUG132', 'OreDeZi'))
            ->toArray(new \DateTimeImmutable(self::NOW));

        self::assertSame('NormaIntreaga', $continut['tipNorma']);
        self::assertSame('TimpOUG132', $continut['timpMunca']['norma']);
    }

    public function testCuiIsSentWithoutTheCountryPrefix(): void
    {
        $detalii = (new DetaliiPropunereMutare(cuiAngajatorDestinatie: ' ro 29451076 ', cuiAngajatorSursa: '13373052'))->toArray();

        self::assertSame('29451076', $detalii['cuiAngajatorDestinatie']);
        self::assertSame('13373052', $detalii['cuiAngajatorSursa']);
        self::assertArrayNotHasKey('cuiAngajatorDestinatie', (new DetaliiPropunereMutare(cuiAngajatorDestinatie: 'RO'))->toArray());
    }

    public function testBonusesDistinguishUnchangedFromCleared(): void
    {
        $now = new \DateTimeImmutable(self::NOW);

        self::assertArrayNotHasKey('sporuriSalariu', $this->continut()->toArray($now));
        self::assertSame([], $this->continut(sporuriSalariu: [])->toArray($now)['sporuriSalariu']);
        self::assertArrayNotHasKey('sporuriSalariu', $this->continut(sporuriSalariu: [new SporSalariu('  ', 5)])->toArray($now));
    }

    public function testEmployerBonusPutsTypeBeforeName(): void
    {
        $angajator = (new SporSalariu(' Merit ', 100, sporAngajator: true))->toArray();
        $predefinit = (new SporSalariu('Vechime', 5, isProcent: true))->toArray();

        self::assertNotNull($angajator);
        self::assertSame(['$type' => 'sporAngajator', 'nume' => 'Merit'], $angajator['tip']);
        self::assertSame(['nume' => 'Vechime'], $predefinit['tip'] ?? null);
        self::assertSame(['tip', 'valoare', 'isProcent'], array_keys($angajator));
    }

    public function testNationalityAndStatelessnessExcludeEachOther(): void
    {
        $citizen = $this->info(nationalitate: 'FRANȚA')->toArray();
        self::assertSame(['nume' => 'FRANȚA'], $citizen['nationalitate']);
        self::assertArrayNotHasKey('apatrid', $citizen);

        $stateless = $this->info(nationalitate: null, apatrid: 'CuDreptDeSedereInUe')->toArray();
        self::assertSame('CuDreptDeSedereInUe', $stateless['apatrid']);
        self::assertArrayNotHasKey('nationalitate', $stateless);

        $both = $this->info(nationalitate: 'FRANȚA', apatrid: 'CuDreptDeSedereInUe')->toArray();
        self::assertArrayNotHasKey('nationalitate', $both);
        self::assertArrayNotHasKey('apatrid', $both);
    }

    public function testLocalityAndForeignLocalityExcludeEachOther(): void
    {
        $romanian = $this->info(localitate: 179141)->toArray();
        self::assertSame(['codSiruta' => 179141], $romanian['localitate']);

        $foreign = $this->info(nationalitate: 'FRANȚA', localitateSalariatStrain: 'Lyon')->toArray();
        self::assertSame('Lyon', $foreign['localitateSalariatStrain']);
        self::assertArrayNotHasKey('localitate', $foreign);

        $both = $this->info(nationalitate: 'FRANȚA', localitate: 179141, localitateSalariatStrain: 'Lyon')->toArray();
        self::assertArrayNotHasKey('localitate', $both);
        self::assertArrayNotHasKey('localitateSalariatStrain', $both);

        $romanianAbroad = $this->info(localitateSalariatStrain: 'Lyon')->toArray();
        self::assertArrayNotHasKey('localitateSalariatStrain', $romanianAbroad);
    }

    public function testForeignerDetailsAreOnlySentForForeignCitizens(): void
    {
        $detalii = new DetaliiSalariatStrain(tipAutorizatie: 'Exceptie', numarAutorizatie: '1234K');

        self::assertArrayHasKey('detaliiSalariatStrain', $this->info(nationalitate: 'FRANȚA', detaliiSalariatStrain: $detalii)->toArray());
        self::assertArrayNotHasKey('detaliiSalariatStrain', $this->info(detaliiSalariatStrain: $detalii)->toArray());
        self::assertArrayNotHasKey('detaliiSalariatStrain', $this->info(nationalitate: 'românia', detaliiSalariatStrain: $detalii)->toArray());
        self::assertArrayNotHasKey('detaliiSalariatStrain', $this->info(nationalitate: 'FRANȚA', detaliiSalariatStrain: new DetaliiSalariatStrain())->toArray());
    }

    public function testCnpIsReducedToLettersAndDigits(): void
    {
        self::assertSame('1800612015459', InfoSalariat::normalizeCnp(' 180 0612-015459 '));
        self::assertSame('AB12', InfoSalariat::normalizeCnp('ab-12'));
        self::assertNull(InfoSalariat::normalizeCnp(' - '));
        self::assertNull(InfoSalariat::normalizeCnp(null));
    }
}
