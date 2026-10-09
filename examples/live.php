<?php

declare(strict_types=1);

/*
 * Helpers for scripts that send real messages to the REGES test environment.
 */

use Slash197\Reges\Envelope;
use Slash197\Reges\Exception\ApiException;
use Slash197\Reges\Exception\ValidationException;
use Slash197\Reges\Message;
use Slash197\Reges\Reges;
use Slash197\Reges\Results\Result;

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

function pretty(mixed $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
