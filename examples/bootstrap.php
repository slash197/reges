<?php

declare(strict_types=1);

/*
 * Builds a client for the REGES test environment from the variables in .env
 * (see .env.example). Scripts start with:
 *
 *     $reges = require __DIR__ . '/../examples/bootstrap.php';
 *
 * Scripts that involve two employers (proposals) get the second registry with
 * regesFor('_2'), which reads REGES_USERNAME_2 and REGES_PASSWORD_2.
 *
 * Set REGES_DEBUG=1 to print request metadata to stderr.
 */

use GuzzleHttp\Client;
use Psr\Log\AbstractLogger;
use slash197\Reges\Config;
use slash197\Reges\Credentials;
use slash197\Reges\Environment;
use slash197\Reges\Reges;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * A client for the registry whose API key is in REGES_USERNAME{$suffix} and REGES_PASSWORD{$suffix}.
 */
function regesFor(string $suffix = ''): Reges
{
    $env = static fn (string $name): string => trim((string) getenv($name), " \"'");

    foreach (['REGES_CLIENT_ID', 'REGES_CLIENT_SECRET', "REGES_USERNAME{$suffix}", "REGES_PASSWORD{$suffix}"] as $name) {
        if ($env($name) === '') {
            fwrite(STDERR, "{$name} is not set. Copy .env.example to .env and fill it in.\n");
            exit(2);
        }
    }

    $logger = $env('REGES_DEBUG') === '' ? null : new class () extends AbstractLogger {
        public function log($level, string|\Stringable $message, array $context = []): void
        {
            fwrite(STDERR, "[{$level}] {$message} " . json_encode($context, JSON_UNESCAPED_SLASHES) . "\n");
        }
    };

    return new Reges(
        new Config(
            Environment::Test,
            $env('REGES_CLIENT_ID'),
            $env('REGES_CLIENT_SECRET'),
            clientApplication: 'slash197/reges',
            clientVersion: 'dev',
            user: $env('REGES_USER') ?: 'Developer',
        ),
        new Credentials($env("REGES_USERNAME{$suffix}"), $env("REGES_PASSWORD{$suffix}")),
        new Client(['timeout' => 30, 'http_errors' => false]),
        logger: $logger,
    );
}

return regesFor();
