<?php

declare(strict_types=1);

namespace slash197\Reges\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use slash197\Reges\Data\ActiuneDetasare;
use slash197\Reges\Data\ActiuneIncetare;
use slash197\Reges\Data\ActiuneReactivare;
use slash197\Reges\Data\ActiuneSuspendare;
use slash197\Reges\Data\ContinutContract;
use slash197\Reges\Data\Cor;
use slash197\Reges\Data\DetaliiPropunereDetasare;
use slash197\Reges\Data\DetaliiPropunereMutare;
use slash197\Reges\Data\DetaliiSalariatStrain;
use slash197\Reges\Data\DocumentJustificativ;
use slash197\Reges\Data\InfoSalariat;
use slash197\Reges\Data\SporSalariu;
use slash197\Reges\Data\TimpMunca;
use slash197\Reges\Message;
use slash197\Reges\Operation;
use slash197\Reges\Support\Dates;
use slash197\Reges\Tests\Support\RegesTestCase;

/**
 * Pins the exact JSON each kind of message produces. The files in
 * tests/golden were reviewed against the integration this package was
 * extracted from; run with UPDATE_GOLDEN=1 to rewrite them after an intended change.
 */
final class GoldenMessageTest extends RegesTestCase
{
    #[DataProvider('names')]
    public function testMessageMatchesGoldenFile(string $name): void
    {
        self::assertSame(file_get_contents(self::file($name)), $this->render($name));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function names(): iterable
    {
        foreach (glob(self::file('*')) ?: [] as $file) {
            yield basename($file, '.json') => [basename($file, '.json')];
        }
    }

    public function testEveryMessageHasAGoldenFile(): void
    {
        $messages = array_keys($this->messages());
        sort($messages);

        if (getenv('UPDATE_GOLDEN')) {
            foreach ($messages as $name) {
                file_put_contents(self::file($name), $this->render($name));
            }
        }

        self::assertSame(array_keys(iterator_to_array(self::names())), $messages);
    }

    private function render(string $name): string
    {
        $message = $this->messages()[$name]->withMessageId(self::MESSAGE_ID)->withSessionId(self::SESSION_ID);

        return json_encode(
            $this->regesWithoutToken()->envelope($message)->payload,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
        ) . "\n";
    }

    private static function file(string $name): string
    {
        return dirname(__DIR__) . "/golden/{$name}.json";
    }

    /**
     * @return array<string, Message>
     */
    private function messages(): array
    {
        return [
            'inregistrare-salariat' => Message::salariat(
                Operation::InregistrareSalariat,
                $this->info(localitate: 179141, dataNastere: Dates::date('1980-06-12')),
            ),

            'modificare-salariat-strain' => Message::salariat(
                Operation::ModificareSalariat,
                new InfoSalariat(
                    cnp: '7900615000011',
                    nume: 'SAMPATH',
                    prenume: 'SUMEDHA',
                    adresa: 'STR. SALARIATULUI, NR. 1',
                    tipActIdentitate: 'Pasaport',
                    taraDomiciliu: 'SRI LANKA',
                    nationalitate: 'SRI LANKA',
                    dataNastere: Dates::date('1990-06-15'),
                    localitateSalariatStrain: 'Colombo',
                    mentiuni: 'Mentiuni salariat',
                    detaliiSalariatStrain: new DetaliiSalariatStrain(
                        tipAutorizatie: 'Exceptie',
                        tipAutorizatieExceptie: 'Art32LiteraK',
                        dataInceputAutorizatie: Dates::date('2026-01-01'),
                        dataSfarsitAutorizatie: Dates::date('2026-09-01'),
                        numarAutorizatie: '1234K',
                    ),
                ),
                self::SALARIAT_ID,
            ),

            'adaugare-contract' => Message::contract(
                Operation::AdaugareContract,
                continut: $this->continut(
                    sporuriSalariu: [
                        new SporSalariu('Spor de vechime', 10, isProcent: true, referinta: '7178f5a4-687f-4da7-928c-1395ec531879'),
                        new SporSalariu('Salariu de merit', 500, referinta: '30195246-89ce-4e33-ac15-f4e8628cab4d', sporAngajator: true),
                    ],
                    tipLocMunca: 'Fix',
                    judetLocMunca: 'CJ',
                    localitateLocMunca: 54975,
                ),
            ),

            'modificare-contract' => Message::contract(
                Operation::ModificareContract,
                self::CONTRACT_ID,
                $this->continut(salariu: 6200.5, dataConsemnare: Dates::date('2020-01-01'), sporuriSalariu: []),
            ),

            'incetare-contract' => Message::contract(
                Operation::IncetareContract,
                self::CONTRACT_ID,
                actiune: new ActiuneIncetare(Dates::date('2026-03-31'), 'Art55LitB', 'Acordul partilor'),
                documentJustificativ: new DocumentJustificativ('Decizie', 'D-17', Dates::date('2026-03-09')),
            ),

            'suspendare-contract' => Message::contract(
                Operation::SuspendareContract,
                self::CONTRACT_ID,
                actiune: new ActiuneSuspendare(Dates::date('2026-04-01'), 'Art51Alin1LitA', Dates::date('2026-09-30'), explicatie: '  '),
                documentJustificativ: new DocumentJustificativ('Cerere', 'C-4', Dates::date('2026-03-09')),
            ),

            'incetare-suspendare-contract' => Message::contract(
                Operation::IncetareSuspendareContract,
                self::CONTRACT_ID,
                actiune: new ActiuneSuspendare(
                    Dates::date('2026-04-01'),
                    'Art51Alin1LitA',
                    Dates::date('2026-09-30'),
                    Dates::date('2026-06-15'),
                ),
                documentJustificativ: new DocumentJustificativ('Cerere', 'C-9', Dates::date('2026-06-10')),
            ),

            'reactivare-contract' => Message::contract(
                Operation::ReactivareContract,
                self::CONTRACT_ID,
                actiune: new ActiuneReactivare(Dates::date('2026-05-01'), 'HotarareJudecatoreasca'),
                documentJustificativ: new DocumentJustificativ('Hotarare', 'H-2', Dates::date('2026-04-28')),
            ),

            'incetare-detasare-contract' => Message::contract(
                Operation::IncetareDetasareContract,
                self::CONTRACT_ID,
                actiune: new ActiuneDetasare(
                    temeiDetasare: 'Transnationala',
                    angajatorCui: '10000002',
                    angajatorNume: 'SAP AG',
                    cor: new Cor(251204, 10),
                    dataInceput: Dates::date('2026-01-01'),
                    dataSfarsit: Dates::date('2026-12-31'),
                    nationalitate: 'GERMANIA',
                    dataIncetareDetasare: Dates::date('2026-06-30'),
                ),
            ),

            'radiere-contract' => Message::contract(
                Operation::RadiereContract,
                self::CONTRACT_ID,
                motivRadiere: '  Inregistrat din eroare ',
            ),

            'propunere-mutare-contract' => Message::propunereMutare(
                Operation::PropunereMutareContract,
                referintaContract: self::CONTRACT_ID,
                detalii: new DetaliiPropunereMutare(
                    tipMutare: 'Transfer',
                    tipTransfer: 'LaCerere',
                    cuiAngajatorDestinatie: 'RO 10000002',
                    numeAngajatorDestinatie: 'DESTINATIE SRL',
                    nationalitateAngajatorDestinatie: 'ROMÂNIA',
                    cuiAngajatorSursa: 'ro10000001',
                    dataPropunere: Dates::date('2026-03-05'),
                    numarPropunere: 'PM-1',
                    dataInceput: Dates::date('2026-04-01'),
                ),
                continutContract: $this->continut(),
                infoSalariat: $this->info(),
                noteSursa: 'Transfer la cerere',
                noteDestinatie: 'not sent with this operation',
            ),

            'acceptare-propunere-mutare-contract' => Message::propunereMutare(
                Operation::AcceptarePropunereMutareContract,
                self::PROPUNERE_ID,
                self::CONTRACT_ID,
                continutContract: $this->continut(),
                infoSalariat: $this->info(),
                noteDestinatie: 'De acord',
            ),

            'propunere-detasare-contract' => Message::propunereDetasare(
                Operation::PropunereDetasareContract,
                referintaContract: self::CONTRACT_ID,
                detalii: new DetaliiPropunereDetasare(
                    temeiDetasare: 'Transnationala',
                    cuiAngajatorDestinatie: '10000002',
                    numeAngajatorDestinatie: 'SAP AG',
                    nationalitateAngajatorDestinatie: 'GERMANIA',
                    cuiAngajatorSursa: 'RO10000001',
                    dataPropunere: Dates::date('2026-03-05'),
                    numarPropunere: 'PD1234',
                    dataInceput: Dates::date('2026-04-01'),
                    dataSfarsit: Dates::date('2026-12-31'),
                ),
                continutContract: $this->continut(),
                infoSalariat: $this->info(),
                noteSursa: 'Detasat cu 2h',
            ),

            'incetare-propunere-detasare-contract' => Message::propunereDetasare(
                Operation::IncetarePropunereDetasareContract,
                self::PROPUNERE_ID,
                self::CONTRACT_ID,
                continutContract: new ContinutContract(),
            ),
        ];
    }
}
