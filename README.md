# reges

PHP client for the [REGES Online](https://reges.inspectiamuncii.ro) API, the Romanian general register of employees run by Inspecția Muncii.

> **Status: work in progress.** Nothing is released yet and the API of this package is not stable.

It covers authentication, sending register operations (employees, contracts, transfer and secondment proposals), reading results safely, nomenclators and employer-defined bonus types. Messages are built from typed objects and validated before they are sent, and the wire-format details REGES is strict about are handled for you.

Names that travel on the wire are kept in Romanian, exactly as in the REGES schema (`ContinutContract`, `dataConsemnare`), so they can be matched against the official documentation. Everything else is in English.

## Requirements

- PHP 8.2+
- A [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client, such as Guzzle

## Usage

```php
use GuzzleHttp\Client;
use Slash197\Reges\Config;
use Slash197\Reges\Credentials;
use Slash197\Reges\Environment;
use Slash197\Reges\Reges;

$reges = new Reges(
    new Config(
        Environment::Test,
        clientId: 'reges-api',
        clientSecret: $clientSecret,
        clientApplication: 'My Payroll',
        clientVersion: '2.4.0',
    ),
    new Credentials($apiUsername, $apiPassword), // Setari -> Acces -> Chei API
    new Client(['timeout' => 30, 'http_errors' => false]),
);
```

Access tokens are requested on demand and reused until a minute before they expire. They are kept in memory unless you pass a `TokenStore` backed by your cache or database.

### Sending a message

```php
use Slash197\Reges\Data\InfoSalariat;
use Slash197\Reges\Message;
use Slash197\Reges\Operation;

$receipt = $reges->send(
    Message::salariat(Operation::InregistrareSalariat, new InfoSalariat(
        cnp: '1800612015459',
        nume: 'POPESCU',
        prenume: 'ION',
        adresa: 'STR. SALARIATULUI, NR. 1',
        tipActIdentitate: 'CarteIdentitate',
        taraDomiciliu: 'România',
        nationalitate: 'România',
    )),
    user: 'Maria Ionescu',
);

$receipt->messageId; // what the result will be matched by
```

`Message::contract()`, `Message::propunereMutare()` and `Message::propunereDetasare()` work the same way. A message that lacks something its operation needs throws a `ValidationException` listing every missing field, before anything is sent.

A receipt only means REGES queued the message. When the submission itself fails, `ApiException::isRetryable()` tells a transient failure (no response, 5xx, 408, 429) from a rejection.

To queue messages yourself, build the final wire form with `$reges->envelope($message)`, store `Envelope::toJson()`, and later send `Envelope::fromJson($json)`.

### Reading results

REGES processes messages asynchronously and puts each outcome in a queue. Results are read in two steps so that none is lost if your code fails halfway:

```php
use Slash197\Reges\Results\Result;

$reges->results()->consume(function (Result $result): void {
    // Store it durably. The queue only advances once this returns;
    // if it throws, the same result is delivered again next time.
    if ($result->isSuccess()) {
        $this->saveRegesId($result->messageId, $result->ref);
    }
});
```

`consume()` returns `null` when the queue is empty. `read()` and `commit()` are available for manual control.

### Nomenclators and bonus types

```php
$reges->nomenclators()->get('TemeiIncetare');
$reges->nomenclators()->all();

$bonus = $reges->bonusTypes()->create('Spor de fidelitate');
```

## Development

Everything runs in Docker; nothing needs to be installed on the host.

```sh
make build     # once
make install
make check     # composer validate, PHPStan, PHPUnit
make smoke     # read-only calls against the REGES test environment, needs .env
make run f=examples/profile.php   # any script, with .env loaded
```

`examples/bootstrap.php` returns a client configured from `.env`. `playground/` is ignored by git and is the place for throwaway scripts.

## Disclaimer

This is an independent, unofficial project. It is not affiliated with or endorsed by Inspecția Muncii. The official integration documentation lives at [reges-ro/integrare](https://github.com/reges-ro/integrare).

## License

MIT, see [LICENSE](LICENSE).
