# reges

[![CI](https://github.com/slash197/reges/actions/workflows/ci.yml/badge.svg)](https://github.com/slash197/reges/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/slash197/reges)](https://packagist.org/packages/slash197/reges)

PHP client for the [REGES Online](https://reges.inspectiamuncii.ro) API, the Romanian general register of employees run by Inspecția Muncii.

> **Status: 0.x.** Usable and tested against the REGES test environment, but the API of this package may still change before 1.0. See the [changelog](CHANGELOG.md).

REGES Online replaced Revisal: employers now report employees and contracts to the labour inspectorate through an API. The official documentation is a schema, a Postman collection and a short readme, and the API is strict in ways none of them spell out. This package was extracted from the REGES integration of [Quanty](https://quanty.ro), an invoicing and business administration platform, and packages what that integration learned:

- **Typed messages.** Employees, contracts, suspensions, terminations, secondments and transfers are built from PHP objects, not hand-assembled arrays.
- **Validation before sending.** A message missing something its operation needs fails locally, with every missing field listed, instead of being rejected by REGES one field at a time.
- **The wire format handled for you.** Endpoint routing, key order, empty objects, dates, the placeholder references proposals need. See [What REGES is strict about](#what-reges-is-strict-about).
- **Results that cannot be lost.** The result queue is read in two steps, so a crash between reading a result and storing it costs nothing.
- **Verified against the real thing.** All 42 supported operations have been run successfully against the REGES test environment, using the scripts in [`examples/`](examples).

Names that travel on the wire are kept in Romanian, exactly as in the REGES schema (`ContinutContract`, `dataConsemnare`), so they can be matched against the official documentation. Everything else is in English.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Getting started](#getting-started)
- [Sending messages](#sending-messages)
- [Reading results](#reading-results)
- [Operations](#operations)
- [Dates](#dates)
- [Errors](#errors)
- [Nomenclators, bonus types and the profile](#nomenclators-bonus-types-and-the-profile)
- [Queueing messages](#queueing-messages)
- [What REGES is strict about](#what-reges-is-strict-about)
- [Rules of the registry worth knowing](#rules-of-the-registry-worth-knowing)
- [What this package does not do](#what-this-package-does-not-do)
- [Development](#development)

## Requirements

- PHP 8.2 or newer, with the `json`, `mbstring` and `simplexml` extensions
- A [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client, such as Guzzle

## Installation

```sh
composer require slash197/reges
```

### Laravel

For a Laravel application, install [slash197/reges-laravel](https://github.com/slash197/reges-laravel) instead. It requires this package and adds what a framework can take care of: the client configured from `.env` and bound in the container, for one company or one API key per tenant; an outbox that stores messages, sends them in order, retries them and matches each to its result; events for the outcome; and a local copy of the nomenclators that validation checks.

```sh
composer require slash197/reges-laravel
```

Messages are built the same way either way, so the rest of this README applies to both.

## Getting started

### Credentials

REGES uses two sets of credentials:

- **An OAuth client id and secret**, the same for every integrator. The ones for the test environment are published in the [official integration docs](https://github.com/reges-ro/integrare).
- **An API key pair per employer registry**, generated in the REGES employer application under *Setări → Acces → Chei API* after selecting the registry. One key pair gives access to exactly one registry: an application reporting for several employers needs one pair, and one client, for each.

On the test environment (`reges.dev.inspectiamuncii.org`) access requests to a registry are approved automatically, so you can try everything below without affecting real data.

### Creating a client

```php
use GuzzleHttp\Client;
use slash197\Reges\Config;
use slash197\Reges\Credentials;
use slash197\Reges\Environment;
use slash197\Reges\Reges;

$reges = new Reges(
    new Config(
        Environment::Test,                // or Environment::Production
        clientId: 'reges-api',
        clientSecret: $clientSecret,
        clientApplication: 'My HR App',  // reported to REGES with every message
        clientVersion: '2.4.0',
        user: 'Maria Ionescu',            // default author; can be set per message instead
    ),
    new Credentials($apiUsername, $apiPassword),
    new Client(['timeout' => 30, 'http_errors' => false]),
);
```

Timeouts and retries are the HTTP client's business; configure them there. If your client throws on 4xx and 5xx responses (Guzzle does by default), turn that off as above so the package can read the response.

The constructor takes a few optional collaborators:

| Argument | Purpose | Default |
|---|---|---|
| `tokenStore` | Where access tokens are kept between requests | in memory, for the life of the object |
| `logger` | A PSR-3 logger for request metadata | none |
| `nomenclatorLookup` | Lets validation check values against your copy of the nomenclators | checks skipped |
| `clock` | A PSR-20 clock, mostly useful in tests | system time |

**Tokens.** An access token is requested on first use and reused until a minute before it expires. To share tokens between processes, implement `slash197\Reges\Auth\TokenStore` (two methods, `get` and `put`) on top of your cache or database; `Token::toArray()` and `Token::fromArray()` are there for serialising.

**Logging.** Only metadata is logged: method, endpoint, status and the request id REGES returns. Message contents are never logged, because they carry personal data such as CNPs and addresses.

## Sending messages

A `Message` is one operation on an employee, a contract or a proposal. `send()` validates it, adds the header and posts it to the right endpoint:

```php
use slash197\Reges\Data\InfoSalariat;
use slash197\Reges\Message;
use slash197\Reges\Operation;

$receipt = $reges->send(Message::salariat(
    Operation::InregistrareSalariat,
    new InfoSalariat(
        cnp: '1800612015459',
        nume: 'POPESCU',
        prenume: 'ION',
        adresa: 'STR. SALARIATULUI, NR. 1',
        tipActIdentitate: 'CarteIdentitate',
        taraDomiciliu: 'România',
        nationalitate: 'România',
    ),
));

echo $receipt->messageId;  // what the result will be matched by
echo $receipt->responseId; // the id REGES gave the submission
```

A receipt only means REGES queued the message. Whether it was accepted is reported later, as a [result](#reading-results) carrying the same `messageId`. For an accepted registration, that result's `ref` is the REGES id of the new employee, which you need for everything that follows.

### Adding a contract

```php
use slash197\Reges\Data\ContinutContract;
use slash197\Reges\Data\Cor;
use slash197\Reges\Data\TimpMunca;
use slash197\Reges\Support\Dates;

$continut = new ContinutContract(
    referintaSalariat: $salariatId,
    cor: new Cor(cod: 251201, versiune: 11),
    dataContract: Dates::date('2026-03-01'),
    dataInceputContract: Dates::date('2026-03-02'),
    numarContract: '127',
    salariu: 5000,
    timpMunca: new TimpMunca(
        norma: 'NormaIntreaga840',
        repartizare: 'OreDeZi',
        durata: 8,
        intervalTimp: 'OrePeZi',
        repartizareMunca: 'Zilnic',
        inceputInterval: Dates::date('2026-03-02')->setTime(9, 0),
        sfarsitInterval: Dates::date('2026-03-02')->setTime(17, 0),
    ),
    tipContract: 'ContractIndividualMunca',
    tipDurata: 'Nedeterminata',
    tipNorma: 'NormaIntreaga',
    tipLocMunca: 'Fix',
    judetLocMunca: 'CJ',
    localitateLocMunca: 54984, // SIRUTA code of Cluj-Napoca
    nivelStudii: 'Superioare',
);

$reges->send(Message::contract(Operation::AdaugareContract, continut: $continut));
```

The codes (`NormaIntreaga840`, `Zilnic`, `Superioare`, the COR code and its version) come from the [nomenclators](#nomenclators-bonus-types-and-the-profile). Salary bonuses go in `sporuriSalariu` as a list of `SporSalariu`.

### Changing a contract

Every later operation refers to the contract by the id from the `AdaugareContract` result:

```php
// A new salary
$reges->send(Message::contract(Operation::ModificareContract, $contractId, $continutCuSalariuNou));
```

Things that happen to a contract, as opposed to changes of its content, are described by an action and usually a supporting document. They do not need the contract content resent:

```php
use slash197\Reges\Data\ActiuneIncetare;
use slash197\Reges\Data\ActiuneSuspendare;
use slash197\Reges\Data\DocumentJustificativ;

// Suspension
$reges->send(Message::contract(
    Operation::SuspendareContract,
    $contractId,
    actiune: new ActiuneSuspendare(
        dataInceput: Dates::date('2026-04-01'),
        temeiLegal: 'Art54',
        dataSfarsit: Dates::date('2026-04-30'),
    ),
    documentJustificativ: new DocumentJustificativ('Decizie', 'D-17', Dates::date('2026-03-25')),
));

// Termination
$reges->send(Message::contract(
    Operation::IncetareContract,
    $contractId,
    actiune: new ActiuneIncetare(Dates::date('2026-06-30'), 'Art55LitB'),
    documentJustificativ: new DocumentJustificativ('Decizie', 'D-31', Dates::date('2026-06-15')),
));
```

There are four kinds of action: `ActiuneIncetare`, `ActiuneSuspendare`, `ActiuneReactivare` and `ActiuneDetasare`.

### Transfers and secondments

Moving an employee to another employer, permanently (*mutare*) or temporarily (*detașare*), is a conversation between two registries. The source employer proposes; the destination employer, with its own API key, accepts or rejects.

```php
use slash197\Reges\Data\DetaliiPropunereDetasare;

// As the source employer
$reges->send(Message::propunereDetasare(
    Operation::PropunereDetasareContract,
    referintaContract: $contractId,
    detalii: new DetaliiPropunereDetasare(
        temeiDetasare: 'CodulMuncii',
        cuiAngajatorDestinatie: '10000002',
        numeAngajatorDestinatie: 'DESTINATIE SRL',
        nationalitateAngajatorDestinatie: 'România',
        cuiAngajatorSursa: '10000001',
        dataPropunere: Dates::date('2026-03-01'),
        numarPropunere: 'PD-12',
        dataInceput: Dates::date('2026-03-02'),
        dataSfarsit: Dates::date('2026-08-31'),
    ),
    continutContract: $continut,
    infoSalariat: $infoSalariat,
    noteSursa: 'Detașare pe proiect',
));

// As the destination employer, with the proposal id from the source's result
$regesDestinatie->send(Message::propunereDetasare(
    Operation::AcceptarePropunereDetasareContract,
    referinta: $propunereId,
    referintaContract: $contractId,
    continutContract: $continut,
    infoSalariat: $infoSalariat,
    noteDestinatie: 'De acord',
));
```

`Message::propunereMutare()` with `DetaliiPropunereMutare` works the same way for transfers.

Once a secondment is accepted, changing, extending or ending it is done by the **source** employer through contract operations with an `ActiuneDetasare` (`PrelungireDetasareContract`, `IncetareDetasareContract` and so on), on its own contract. Cancelling an accepted transfer (`AnulareTransferContract`) is likewise the source's operation.

### Validation

Each named constructor accepts every part its message type can carry, all optional. What an operation needs is checked when the message is sent:

```php
use slash197\Reges\Exception\ValidationException;

try {
    $reges->send(Message::contract(Operation::ModificareContract, continut: new ContinutContract()));
} catch (ValidationException $exception) {
    $exception->fields(); // ['referintaContract.id', 'continut.referintaSalariat.id', 'continut.cor.cod', ...]
    $exception->errors;   // field => the rule it broke: 'required', 'uuid', 'max:256', 'in:nationalitate'
}
```

A part the operation does not use is left out of the message rather than rejected, so one set of objects can be passed to several operations.

By default validation checks structure only. To also check values against the nomenclators (country names, identity document types, locality codes), pass an implementation of `slash197\Reges\Validation\NomenclatorLookup` backed by your local copy.

### Who is sending

REGES records two identities with each message. The *author* is the API key, taken from the credentials. The *user* is the person in your application on whose behalf the message is sent, a free-text name: set a default in `Config`, or pass it per message with `$reges->send($message, user: 'Maria Ionescu')`.

## Reading results

REGES processes messages asynchronously, in the order received, and puts the outcome of each in a queue belonging to the registry. Results are read one at a time, in two steps: reading does not advance the queue, committing does.

```php
use slash197\Reges\Results\Result;

$result = $reges->results()->consume(function (Result $result): void {
    // Store the outcome durably here. The queue only advances once this
    // returns; if it throws, the same result is delivered again next time.
    $this->outcomes->record($result->messageId, $result->isSuccess(), $result->ref, $result->description);
});

// $result is the result that was handled, or null if the queue was empty
```

Call it in a loop, or on a schedule, until it returns `null`. For manual control there are `read()` and `commit()`.

REGES also offers a one-step poll that reads and advances together. It is deliberately not exposed: a crash between receiving a result and storing it would lose that result for good.

A `Result` has:

| Property | Meaning |
|---|---|
| `messageId` | The id of the message this is the outcome of |
| `isSuccess()` | Whether REGES accepted the operation (`code` is `SUCCES`) |
| `code`, `codeType`, `description` | The outcome as REGES words it; `description` is the human-readable explanation of a rejection |
| `ref` | The id of what the operation created, see below |
| `secRef` | A second reference, returned by proposal operations only |
| `operation` / `operation()` | The operation name as sent by REGES, and as an `Operation` case |
| `relatedResultsExpected` | True when more results will follow for the same message |
| `raw` | The result exactly as received |

What `ref` identifies depends on the operation, and you will want to store it:

| Operation | `ref` is the id of |
|---|---|
| `InregistrareSalariat` | the new employee |
| `AdaugareContract` | the new contract |
| `PropunereMutareContract`, `PropunereDetasareContract` | the new proposal |
| `AcceptarePropunere…Contract` | the contract REGES created in the destination's registry |
| any other contract operation | the entry the operation added to the contract's history |

The history entry ids are what `CorectieIstoricContract` and `RadiereIstoricContract` later refer to. A few operations, such as `RadiereSalariat`, return no `ref`.

**Several applications, one registry.** Each reader of the queue has its own position, identified by a consumer id: `$reges->results('hr-app')`. Applications sharing a registry each need their own, or they take each other's results. A new consumer id starts from the beginning of the queue.

## Operations

All 42 operations are cases of the `Operation` enum, named as in the REGES schema.

| Message | Operations |
|---|---|
| `Message::salariat()` | `InregistrareSalariat`, `ModificareSalariat`, `CorectieSalariat`, `RadiereSalariat` |
| `Message::contract()`, content | `AdaugareContract`, `ModificareContract`, `CorectieContract`, `RadiereContract` |
| termination | `IncetareContract`, `CorectieIncetareContract`, `AnulareIncetareContract` |
| reactivation | `ReactivareContract`, `AnulareReactivareContract` |
| suspension | `SuspendareContract`, `ModificareSuspendareContract`, `IncetareSuspendareContract`, `CorectieIncetareSuspendareContract`, `AnulareSuspendareContract` |
| accepted secondment | `PrelungireDetasareContract`, `ModificareDetasareContract`, `CorectieDetasareContract`, `IncetareDetasareContract`, `CorectieIncetareDetasareContract`, `AnulareIncetareDetasareContract`, `AnulareDetasareContract` |
| accepted transfer | `AnulareTransferContract` |
| history | `CorectieIstoricContract`, `RadiereIstoricContract`, `AdaugareModificareInIstoricContract`, `AdaugareSuspendareInIstoricContract`, `CorectieIstoricContractCuPropagare`, `AdaugareModificareInIstoricContractCuPropagare` |
| `Message::propunereDetasare()` | `PropunereDetasareContract`, `ModificarePropunereDetasareContract`, `AcceptarePropunereDetasareContract`, `RespingerePropunereDetasareContract`, `RadierePropunereDetasareContract`, `IncetarePropunereDetasareContract` |
| `Message::propunereMutare()` | `PropunereMutareContract`, `AcceptarePropunereMutareContract`, `RespingerePropunereMutareContract`, `RadierePropunereMutareContract` |

The vocabulary, for readers of the schema: *Modificare* is a change from now on, *Corecție* fixes a mistake in what was reported, *Anulare* undoes an operation, *Radiere* strikes a record off as entered in error, and *Încetare* ends something.

Operations on employers themselves, access requests and exports are not covered.

## Dates

REGES carries dates as date-times, and records the **UTC date** of what it receives. Midnight Romanian time is still the previous evening in UTC, so a naive `new DateTimeImmutable('2026-03-01', new DateTimeZone('Europe/Bucharest'))` lands in the registry as 28 February.

Use `Dates::date()` for calendar days:

```php
use slash197\Reges\Support\Dates;

Dates::date('2026-03-01'); // recorded by REGES as 1 March
```

Any `DateTimeInterface` is accepted, and is sent in Romanian local time with its offset. For actual moments in time that is all there is to it; only calendar days need the care above.

`dataConsemnare`, the date a contract's content takes effect, is mostly decided for you:

- on `AdaugareContract` it is the value you give, or the contract's start date;
- on the history operations it is the value you give, since they place a change in the past;
- on everything else it is the moment the message is built. REGES rejects a value that does not move forward from one message to the next.

## Errors

Every exception extends `slash197\Reges\Exception\RegesException`.

| Exception | When | What to do |
|---|---|---|
| `ValidationException` | The message lacks something its operation needs. Nothing was sent. | Fix the data; `errors` names each field |
| `AuthenticationException` | The token request was refused or the credentials are missing | Check the credentials; `error` and `errorDescription` carry what the server said |
| `ApiException` | A request did not get the expected answer | See `isRetryable()` |

`ApiException::isRetryable()` separates a transient failure, where the same request may succeed later (no response at all, a 5xx, 408 or 429), from a rejection that will fail the same way again (any other status, typically a 400 with the schema problems in `body`). It also carries `statusCode` and the `requestId` to quote to REGES support.

A message REGES accepts for processing and then rejects is not an exception: it is a `Result` whose `isSuccess()` is false, with the reason in `description`.

## Nomenclators, bonus types and the profile

**Nomenclators** are the reference lists messages are validated against: countries, COR occupations, localities, legal grounds and so on. They change over time, so keep a local copy and refresh it periodically.

```php
$reges->nomenclators()->get('TemeiIncetare'); // one list
$reges->nomenclators()->all();                // every public list in one large download
```

The public lists need no authentication. Country fields take the country's *name* exactly as the `Nationalitate` nomenclator spells it (`România`); most other fields take the `cod`.

**Bonus types** an employer defines for itself, to refer to in `sporuriSalariu`, are managed separately and do need authentication:

```php
$reges->nomenclators()->tipSporAngajator();              // the ones defined so far

$bonus = $reges->bonusTypes()->create('Spor de fidelitate');
$reges->bonusTypes()->update($bonus->id, 'Spor de loialitate');
$reges->bonusTypes()->delete($bonus->id, 'Spor de loialitate');
```

**The profile** is the employer registry behind the API key: `$reges->profile()` returns it, and `$reges->angajatorId()` the employer's REGES id.

## Queueing messages

Applications that report from a queue, to retry on failure and keep each contract's messages in order, need to store a message and send it later. The [Laravel bridge](https://github.com/slash197/reges-laravel) does this for you; elsewhere, an `Envelope` is a message in its final wire form:

```php
use slash197\Reges\Envelope;

$envelope = $reges->envelope($message);        // validated, header added, not sent
$outbox->store($envelope->messageId(), $envelope->toJson());

// later, possibly in another process
$receipt = $reges->send(Envelope::fromJson($json));
```

Use `toJson()` and `fromJson()` rather than your own `json_decode`/`json_encode`: a plain round trip turns the empty objects REGES insists on into empty arrays.

One thing to plan for: an envelope carries the `dataConsemnare` of the moment it was built. If it sits in a queue while a later-built message for the same contract is sent first, REGES will refuse it as out of order. Send each contract's messages in the order they were built.

## What REGES is strict about

These are the behaviours of the API that the package exists to absorb. Each has a test.

| Behaviour | Handled by |
|---|---|
| Proposal messages have their own endpoints; sent to `/api/Contract` they intermittently come back as a bare 500 | routing by message type |
| `$type` must be the first key of every polymorphic object, or the message is rejected with a 400 | the builders |
| `stareCurenta` must be present as an empty object `{}`; an empty array `[]` is rejected | the builders, and `Envelope` for stored messages |
| Proposal content needs a currency and the all-zero id in place of the employee reference | `Message::propunereMutare()` / `propunereDetasare()` |
| `dataConsemnare` must advance with each message on a contract | set at build time, see [Dates](#dates) |
| The UTC date of a date-time is what gets recorded | `Dates::date()` |
| Contracts recorded from 1 April 2025 need the work schedule, workplace and education level, some of it conditionally | validation |
| `tipNorma` and `timpMunca.norma` describe the same thing with two different code sets | converted in both directions on the way out |
| Nationality and statelessness exclude each other, as do the Romanian and the foreign locality | `InfoSalariat` sends neither if given both |
| Fiscal codes are expected without the `RO` prefix | stripped in proposal details |
| Results arrive in camelCase or PascalCase, flat or nested under `result` | `Result::fromPayload()` |
| Reading and advancing the result queue in one call loses a result on a crash | two-step read, see [Reading results](#reading-results) |
| The author of a message is the API username, and must be a UUID | taken from the credentials and validated |
| The bonus-type endpoint takes XML and answers in JSON or XML | `bonusTypes()` |

## Rules of the registry worth knowing

These are not quirks of the wire format but of how REGES reasons about contracts. The package cannot enforce them, and they are not written down elsewhere:

- **A future-dated event blocks the contract.** A secondment proposed to start next week puts a future-dated entry in the contract's history. REGES then refuses any operation recorded before that date, and answers a request to end the proposal with an unspecific error.
- **A contract proposed for transfer stays marked as moved**, even after the proposal is withdrawn or rejected, and takes no further proposal.
- **Extending or changing a secondment must move its end date later.**
- **Cancelling needs the state it cancels.** `AnulareSuspendareContract` works only while the contract is suspended; once the suspension has ended there is nothing to cancel.
- **The contract REGES creates at the destination** of a secondment or transfer is a separate contract with its own id. The source's contract is the one marked as seconded or transferred, and the one later operations act on.
- **Adding to the history** refers to the entry the new one goes after. The contract's own id stands for its first entry.

## What this package does not do

It is a client for the API and stops there. Deliberately left to the application:

- storing messages and results, and matching one to the other;
- queueing, ordering messages per contract, retry schedules and backoff;
- deciding which operation a change in your data amounts to;
- keeping a local copy of the nomenclators.

For Laravel, [slash197/reges-laravel](https://github.com/slash197/reges-laravel) takes on the first two, and keeps a copy of the nomenclators that validation checks.

## Development

Everything runs in Docker; nothing needs to be installed on the host.

```sh
make build     # once
make install
make check     # composer validate, PHPStan, PHPUnit
```

The unit tests need no network. They include golden files (`tests/golden`) pinning the exact JSON each kind of message produces; `UPDATE_GOLDEN=1` rewrites them after an intended change.

### Running against the test environment

Copy `.env.example` to `.env` and fill in an API key for a test registry.

```sh
make smoke                                   # read-only: authentication, profile, a nomenclator, a peek at the queue
make run f=examples/register-salariat.php    # any script, with .env loaded
```

The scripts in [`examples/`](examples) send real messages to the test registry and wait for their results. Between them they exercise every operation:

| Script | Covers |
|---|---|
| `register-salariat.php`, `contract.php` | an employee and the basic life of a contract |
| `suspension.php` | suspending a contract and ending the suspension |
| `history.php` | correcting and striking off history, a contract and an employee |
| `variants.php` | corrections and cancellations; adding to a contract's history |
| `proposals.php` | secondments and transfers between two registries (needs a second API key) |
| `bonus-type.php` | creating, renaming and deleting a bonus type |

They create records that stay in the test registry. `examples/bootstrap.php` returns a client configured from `.env`, and `playground/` is ignored by git for throwaway scripts.

## Disclaimer

This is an independent, unofficial project. It is not affiliated with or endorsed by Inspecția Muncii. The official integration documentation lives at [reges-ro/integrare](https://github.com/reges-ro/integrare).

## License

MIT, see [LICENSE](LICENSE).
