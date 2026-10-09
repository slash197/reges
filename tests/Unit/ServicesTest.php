<?php

declare(strict_types=1);

namespace Slash197\Reges\Tests\Unit;

use Psr\Log\AbstractLogger;
use Slash197\Reges\Exception\ApiException;
use Slash197\Reges\Message;
use Slash197\Reges\Operation;
use Slash197\Reges\Tests\Support\RegesTestCase;

final class ServicesTest extends RegesTestCase
{
    private const ANGAJATOR_ID = '9d2f6c1e-7a4b-4f0e-9c3d-2b1a0f9e8d7c';

    public function testProfileExposesTheEmployerId(): void
    {
        $reges = $this->reges();
        $this->http->respond(200, ['referintaAngajator' => ['id' => ' ' . self::ANGAJATOR_ID . ' '], 'denumire' => 'ACME SRL']);

        $profile = $reges->profile();

        self::assertSame('GET', $this->http->last()->getMethod());
        self::assertSame('https://api.dev.inspectiamuncii.org/api/Profile', (string) $this->http->last()->getUri());
        self::assertSame('application/json', $this->http->last()->getHeaderLine('Accept'));
        self::assertSame(self::ANGAJATOR_ID, $profile->angajatorId);
        self::assertSame('ACME SRL', $profile->raw['denumire']);
    }

    public function testEmployerIdIsFetchedOnce(): void
    {
        $reges = $this->reges();
        $this->http->respond(200, ['referintaAngajator' => ['id' => self::ANGAJATOR_ID]]);

        $reges->angajatorId();

        self::assertSame(self::ANGAJATOR_ID, $reges->angajatorId());
        self::assertCount(1, $this->http->requests);
    }

    public function testProfileWithoutAnEmployerIdThrowsWhenTheIdIsNeeded(): void
    {
        $reges = $this->reges();
        $this->http->respond(200, []);

        $this->expectException(ApiException::class);
        $reges->angajatorId();
    }

    public function testPublicNomenclatorsAreFetchedWithoutAToken(): void
    {
        $reges = $this->regesWithoutToken();
        $this->http->respond(200, ['Cor' => [['cod' => 251204]]])->respond(200, [['cod' => 'MG']]);

        self::assertSame(['Cor' => [['cod' => 251204]]], $reges->nomenclators()->all());
        self::assertSame('https://api.dev.inspectiamuncii.org/api/Nomenclator?tip=toate', (string) $this->http->last()->getUri());
        self::assertFalse($this->http->last()->hasHeader('Authorization'));

        self::assertSame([['cod' => 'MG']], $reges->nomenclators()->get('NivelStudii'));
        self::assertSame('tip=NivelStudii', $this->http->last()->getUri()->getQuery());
        self::assertCount(2, $this->http->requests);
    }

    public function testEmployerBonusTypesNeedATokenAndTheEmployerId(): void
    {
        $reges = $this->reges();
        $this->http->respond(200, ['referintaAngajator' => ['id' => self::ANGAJATOR_ID]]);
        $this->http->respond(200, [['id' => 's-1', 'nume' => 'Merit']]);

        self::assertSame([['id' => 's-1', 'nume' => 'Merit']], $reges->nomenclators()->tipSporAngajator());
        self::assertSame('tip=tipsporangajator&angajatorId=' . self::ANGAJATOR_ID, $this->http->last()->getUri()->getQuery());
        self::assertSame('Bearer test-token', $this->http->last()->getHeaderLine('Authorization'));
    }

    public function testFailedNomenclatorFetchThrows(): void
    {
        $this->http->respond(500);

        $this->expectException(ApiException::class);
        $this->regesWithoutToken()->nomenclators()->all();
    }

    /**
     * The bonus-type endpoint is the one place REGES takes XML.
     */
    public function testBonusTypeIsCreatedWithXml(): void
    {
        $reges = $this->regesWithEmployer();
        $this->http->respond(200, ['id' => 's-1', 'name' => 'Merit & Co']);

        $bonus = $reges->bonusTypes()->create('Merit & <Co>');

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.dev.inspectiamuncii.org/api/Nomenclator/tipsporangajator', (string) $request->getUri());
        self::assertSame('text/xml', $request->getHeaderLine('Content-Type'));
        self::assertSame('text/xml', $request->getHeaderLine('Accept'));
        self::assertSame(
            "<TipSporAngajator>\n<Nume>Merit &amp; &lt;Co&gt;</Nume>\n<AngajatorId>" . self::ANGAJATOR_ID . "</AngajatorId>\n</TipSporAngajator>",
            (string) $request->getBody(),
        );
        self::assertSame('s-1', $bonus->id);
        self::assertSame('Merit & Co', $bonus->name);
    }

    public function testBonusTypeAnswerMayBeXml(): void
    {
        $reges = $this->regesWithEmployer();
        $this->http->respond(200, '<TipSporAngajator><Id> s-2 </Id><Nume>Vechime</Nume></TipSporAngajator>');

        $bonus = $reges->bonusTypes()->create('Vechime');

        self::assertSame('s-2', $bonus->id);
        self::assertSame('Vechime', $bonus->name);
        self::assertSame(['id' => 's-2', 'name' => 'Vechime'], $bonus->raw);
    }

    public function testCreatedBonusTypeWithoutAnIdThrows(): void
    {
        $reges = $this->regesWithEmployer();
        $this->http->respond(200, '');

        $this->expectException(ApiException::class);
        $reges->bonusTypes()->create('Merit');
    }

    public function testBonusTypeUpdateSendsTheIdAndFallsBackToWhatWasSent(): void
    {
        $reges = $this->regesWithEmployer();
        $this->http->respond(200, '');

        $bonus = $reges->bonusTypes()->update('s-1', 'Merit');

        self::assertSame('POST', $this->http->last()->getMethod());
        self::assertSame(
            "<TipSporAngajator>\n<Id>s-1</Id>\n<Nume>Merit</Nume>\n<AngajatorId>" . self::ANGAJATOR_ID . "</AngajatorId>\n</TipSporAngajator>",
            (string) $this->http->last()->getBody(),
        );
        self::assertSame('s-1', $bonus->id);
        self::assertSame('Merit', $bonus->name);
    }

    public function testBonusTypeDeleteSendsTheFullRecord(): void
    {
        $reges = $this->regesWithEmployer();
        $this->http->respond(200);

        $reges->bonusTypes()->delete('s-1', 'Merit');

        self::assertSame('DELETE', $this->http->last()->getMethod());
        self::assertStringContainsString('<Id>s-1</Id>', (string) $this->http->last()->getBody());
    }

    public function testFailedBonusTypeRequestThrows(): void
    {
        $reges = $this->regesWithEmployer();
        $this->http->respond(400, 'bad');

        $this->expectException(ApiException::class);
        $reges->bonusTypes()->delete('s-1', 'Merit');
    }

    /**
     * Payloads carry CNPs and addresses; tokens and passwords are secrets.
     */
    public function testLogsCarryMetadataOnly(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lines[] = $level . ' ' . $message . ' ' . json_encode($context);
            }
        };

        $reges = $this->regesWithoutToken($logger);
        $this->http->respond(200, ['access_token' => 'secret-token', 'expires_in' => 300]);
        $this->http->respond(200, ['responseId' => 'r-1'], ['trace-id' => 'trace-7']);
        $this->http->fail('boom');

        $message = Message::salariat(Operation::InregistrareSalariat, $this->info());
        $reges->send($message);
        try {
            $reges->send($message);
        } catch (ApiException) {
        }

        $log = implode("\n", $logger->lines);

        self::assertStringContainsString('"endpoint":"https:\/\/api.dev.inspectiamuncii.org\/api\/Salariat"', $log);
        self::assertStringContainsString('"status":200', $log);
        self::assertStringContainsString('"request_id":"trace-7"', $log);
        self::assertStringContainsString('warning REGES request failed', $log);

        foreach (['1800612015459', 'POPESCU', 'STR. SALARIATULUI', 'secret-token', 'api-password', 'client-secret'] as $sensitive) {
            self::assertStringNotContainsString($sensitive, $log);
        }
    }

    private function regesWithEmployer(): \Slash197\Reges\Reges
    {
        $reges = $this->reges();
        $this->http->respond(200, ['referintaAngajator' => ['id' => self::ANGAJATOR_ID]]);
        $reges->profile();
        $this->http->requests = [];

        return $reges;
    }
}
