<?php

declare(strict_types=1);

namespace slash197\Reges\Tests\Unit;

use slash197\Reges\Auth\Token;
use slash197\Reges\Config;
use slash197\Reges\Credentials;
use slash197\Reges\Environment;
use slash197\Reges\Exception\ApiException;
use slash197\Reges\Exception\AuthenticationException;
use slash197\Reges\Reges;
use slash197\Reges\Tests\Support\RegesTestCase;

final class AuthTest extends RegesTestCase
{
    public function testTokenIsRequestedWithThePasswordGrant(): void
    {
        $this->http->respond(200, ['access_token' => ' abc ', 'expires_in' => 300, 'token_type' => 'Bearer', 'refresh_token' => 'r']);

        $token = $this->regesWithoutToken()->authenticate();

        $request = $this->http->last();
        parse_str((string) $request->getBody(), $form);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://sso.dev.inspectiamuncii.org/realms/API/protocol/openid-connect/token', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'grant_type' => 'password',
            'client_id' => 'reges-api',
            'client_secret' => 'client-secret',
            'username' => self::USERNAME,
            'password' => 'api-password',
        ], $form);

        self::assertSame('abc', $token->accessToken);
        self::assertSame('Bearer', $token->tokenType);
        self::assertSame('r', $token->refreshToken);
        self::assertEquals(new \DateTimeImmutable('2026-03-10T09:35:00+02:00'), $token->expiresAt);
    }

    public function testTokenIsReusedUntilAMinuteBeforeItExpires(): void
    {
        $reges = $this->regesWithoutToken();
        $this->http->respond(200, ['access_token' => 'first', 'expires_in' => 300]);
        $this->http->respond(200, ['referintaAngajator' => ['id' => 'a-1']]);
        $reges->profile();
        self::assertCount(2, $this->http->requests);

        $this->clock->advance('+239 seconds');
        $this->http->respond(200, []);
        $reges->profile();
        self::assertCount(3, $this->http->requests, 'still more than a minute left: no new token');
        self::assertSame('Bearer first', $this->http->last()->getHeaderLine('Authorization'));

        $this->clock->advance('+1 second');
        $this->http->respond(200, ['access_token' => 'second', 'expires_in' => 300]);
        $this->http->respond(200, []);
        $reges->profile();
        self::assertCount(5, $this->http->requests);
        self::assertSame('Bearer second', $this->http->last()->getHeaderLine('Authorization'));
    }

    public function testTokenWithoutAnExpiryIsNeverReused(): void
    {
        $reges = $this->regesWithoutToken();
        $this->http->respond(200, ['access_token' => 'first'])->respond(200, []);
        $this->http->respond(200, ['access_token' => 'second'])->respond(200, []);

        $reges->profile();
        $reges->profile();

        self::assertCount(4, $this->http->requests);
        self::assertSame('Bearer second', $this->http->last()->getHeaderLine('Authorization'));
    }

    public function testTokenStoreIsSharedBetweenClientsOfTheSameCredentials(): void
    {
        $this->reges();
        $second = $this->regesWithoutToken();
        $this->http->respond(200, []);

        $second->profile();

        self::assertCount(1, $this->http->requests);
        self::assertSame('Bearer test-token', $this->http->last()->getHeaderLine('Authorization'));
    }

    public function testTokenIsNotSharedWithOtherCredentials(): void
    {
        $this->reges();
        $other = $this->regesWithoutToken(username: 'aaaaaaaa-daea-480e-a945-bdd3579477ef');
        $this->http->respond(200, ['access_token' => 'other', 'expires_in' => 300])->respond(200, []);

        $other->profile();

        self::assertSame('Bearer other', $this->http->last()->getHeaderLine('Authorization'));
    }

    public function testRejectedCredentialsThrow(): void
    {
        $this->http->respond(401, ['error' => 'invalid_grant', 'error_description' => 'Invalid user credentials']);

        try {
            $this->regesWithoutToken()->authenticate();
            self::fail('Expected an AuthenticationException.');
        } catch (AuthenticationException $exception) {
            self::assertSame(401, $exception->statusCode);
            self::assertSame('invalid_grant', $exception->error);
            self::assertSame('Invalid user credentials', $exception->errorDescription);
        }
    }

    public function testResponseWithoutATokenThrows(): void
    {
        $this->http->respond(200, ['token_type' => 'Bearer']);

        $this->expectException(AuthenticationException::class);
        $this->regesWithoutToken()->authenticate();
    }

    public function testMissingCredentialsThrowWithoutCallingReges(): void
    {
        $reges = new Reges(
            new Config(Environment::Test, 'reges-api', 'secret', 'Payroll', '1.0'),
            new Credentials('  ', ''),
            $this->http,
        );

        try {
            $reges->authenticate();
            self::fail('Expected an AuthenticationException.');
        } catch (AuthenticationException $exception) {
            self::assertSame('missing_credentials', $exception->error);
            self::assertSame([], $this->http->requests);
        }
    }

    public function testUnreachableTokenEndpointIsRetryable(): void
    {
        $this->http->fail();

        try {
            $this->regesWithoutToken()->authenticate();
            self::fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            self::assertTrue($exception->isRetryable());
        }
    }

    public function testTokenRoundTripsThroughAnArray(): void
    {
        $token = new Token('abc', new \DateTimeImmutable('2026-03-10T09:35:00+02:00'), 'r', 'Bearer');

        self::assertEquals($token, Token::fromArray($token->toArray()));
        self::assertEquals(new Token('abc'), Token::fromArray((new Token('abc'))->toArray()));
    }

    public function testConfigUsesTheEnvironmentUnlessOverridden(): void
    {
        $production = new Config(Environment::Production, 'id', 'secret', 'Payroll', '1.0');
        self::assertSame('https://api.inspectiamuncii.ro', $production->apiUrl);

        $custom = new Config(Environment::Test, 'id', 'secret', 'Payroll', '1.0', apiUrl: 'https://proxy.test/', tokenUrl: 'https://proxy.test/token');
        self::assertSame('https://proxy.test', $custom->apiUrl);
        self::assertSame('https://proxy.test/token', $custom->tokenUrl);

        $this->expectException(\InvalidArgumentException::class);
        new Config(Environment::Test, 'id', 'secret', ' ', '1.0');
    }
}
