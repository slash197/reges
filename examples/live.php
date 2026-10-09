<?php

declare(strict_types=1);

/*
 * Helpers for scripts that send real messages to the REGES test environment.
 */

use slash197\Reges\Data\ContinutContract;
use slash197\Reges\Data\Cor;
use slash197\Reges\Data\TimpMunca;
use slash197\Reges\Envelope;
use slash197\Reges\Exception\ApiException;
use slash197\Reges\Exception\ValidationException;
use slash197\Reges\Message;
use slash197\Reges\Reges;
use slash197\Reges\Results\Result;
use slash197\Reges\Support\Dates;

/**
 * A CNP with a valid checksum for a made-up man born on 12 June 1980.
 */
function fakeCnp(): string
{
    $digits = '1800612' . '12' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    $sum = 0;
    foreach (str_split('279146358279') as $i => $weight) {
        $sum += (int) $digits[$i] * (int) $weight;
    }
    $control = $sum % 11;

    return $digits . ($control === 10 ? 1 : $control);
}

/**
 * Sends a message, then waits for its result and commits it. A result that
 * belongs to some other message is left in the queue, uncommitted.
 */
function sendAndAwait(Reges $reges, Message $message, int $timeoutSeconds = 90): ?Result
{
    try {
        $envelope = $reges->envelope($message);
    } catch (ValidationException $exception) {
        echo "Not sent: {$exception->getMessage()}\n";

        return null;
    }

    echo "-> {$envelope->operation()} {$envelope->messageId()}\n";
    if (getenv('REGES_SHOW_PAYLOAD')) {
        echo pretty(Envelope::fromJson($envelope->toJson())->payload), "\n";
    }

    try {
        $receipt = $reges->send($envelope);
    } catch (ApiException $exception) {
        echo "   submission failed: HTTP {$exception->statusCode}, retryable: ", var_export($exception->isRetryable(), true), "\n";
        echo '   ', $exception->body, "\n";

        return null;
    }

    echo "   queued, HTTP {$receipt->statusCode}, responseId {$receipt->responseId}\n";

    $deadline = time() + $timeoutSeconds;
    while (time() < $deadline) {
        $result = $reges->results()->read();

        if ($result === null) {
            sleep(3);
            continue;
        }

        if ($result->messageId !== $receipt->messageId) {
            echo "   the queue holds a result for another message ({$result->operation} {$result->messageId}); leaving it uncommitted\n";

            return null;
        }

        $reges->results()->commit();
        echo '<- ', $result->isSuccess() ? 'SUCCES' : "FAILED ({$result->code})", ", ref {$result->ref}\n";
        echo pretty($result->raw), "\n";

        return $result;
    }

    echo "   no result after {$timeoutSeconds}s\n";

    return null;
}

/**
 * The calendar day it is now in Romania, optionally shifted ("+1 day").
 */
function today(string $modifier = '+0 days'): DateTimeImmutable
{
    $day = (new DateTimeImmutable('now', new DateTimeZone(Dates::TIMEZONE)))->format('Y-m-d');

    return Dates::date($day)->modify($modifier);
}

/**
 * A complete full-time contract for the given employee: an analyst with a
 * daily 09:00-17:00 schedule at a fixed workplace.
 */
function testContinut(string $salariatId, string $numarContract, DateTimeImmutable $start, int $salariu): ContinutContract
{
    return new ContinutContract(
        referintaSalariat: $salariatId,
        cor: new Cor(251201, 11),
        dataContract: $start->modify('-1 day'),
        dataInceputContract: $start,
        numarContract: $numarContract,
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
}

/**
 * Runs a sequence of messages, remembering in a playground file the "ref" each
 * one returned. A run that stops at a failed step picks up there next time.
 */
final class Progress
{
    /** @var array<string, mixed> */
    public array $state;

    private string $file;

    public function __construct(private Reges $reges, string $name)
    {
        $this->file = dirname(__DIR__) . "/playground/{$name}.json";
        $this->state = is_file($this->file)
            ? json_decode((string) file_get_contents($this->file), true, 512, JSON_THROW_ON_ERROR)
            : [];
    }

    /**
     * @param callable(): Message $message Built only when the step actually runs
     */
    public function step(string $name, callable $message): void
    {
        if (isset($this->state[$name])) {
            echo "== {$name}: already done\n";

            return;
        }

        echo "== {$name}\n";
        $result = sendAndAwait($this->reges, $message());

        if (!$result?->isSuccess()) {
            exit(1);
        }

        $this->state[$name] = $result->ref ?? true;
        $this->save();
    }

    public function save(): void
    {
        file_put_contents($this->file, pretty($this->state) . "\n");
    }
}

function pretty(mixed $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
