<?php

declare(strict_types=1);

namespace Slash197\Reges\Tests\Support;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Slash197\Reges\Auth\InMemoryTokenStore;
use Slash197\Reges\Auth\Token;
use Slash197\Reges\Config;
use Slash197\Reges\Credentials;
use Slash197\Reges\Data\ContinutContract;
use Slash197\Reges\Data\Cor;
use Slash197\Reges\Data\InfoSalariat;
use Slash197\Reges\Data\TimpMunca;
use Slash197\Reges\Environment;
use Slash197\Reges\Reges;
use Slash197\Reges\Support\Dates;
use Slash197\Reges\Validation\NomenclatorLookup;

abstract class RegesTestCase extends TestCase
{
    protected const USERNAME = 'caee860a-daea-480e-a945-bdd3579477ef';
    protected const NOW = '2026-03-10T09:30:00+02:00';
    protected const SALARIAT_ID = 'beee0a8b-4dca-41bc-906c-e79627452b1e';
    protected const CONTRACT_ID = '0c2e8adf-44dc-4a41-b1e5-3c4013f306ac';
    protected const PROPUNERE_ID = '3571af32-5352-43e7-bba9-1018b33c0749';
    protected const MESSAGE_ID = '117f9b03-9efb-4f5e-8eab-7ab3b0c792af';
    protected const SESSION_ID = '117f9b04-9efb-4f5e-8ebb-7ab3b0c792cf';

    protected FakeHttpClient $http;
    protected FixedClock $clock;
    protected InMemoryTokenStore $tokens;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->clock = new FixedClock(new \DateTimeImmutable(self::NOW));
        $this->tokens = new InMemoryTokenStore();
    }

    /**
     * A client that already holds a valid token, so tests only see the calls they are about.
     */
    protected function reges(
        ?LoggerInterface $logger = null,
        ?NomenclatorLookup $lookup = null,
        string $username = self::USERNAME,
    ): Reges {
        $reges = $this->regesWithoutToken($logger, $lookup, $username);

        // Seeds the store under the key the client will look up.
        $this->http->respond(200, ['access_token' => 'test-token', 'expires_in' => 3600]);
        $reges->authenticate();
        $this->http->requests = [];

        return $reges;
    }

    protected function regesWithoutToken(
        ?LoggerInterface $logger = null,
        ?NomenclatorLookup $lookup = null,
        string $username = self::USERNAME,
    ): Reges {
        return new Reges(
            new Config(Environment::Test, 'reges-api', 'client-secret', 'Payroll', '1.0', user: 'Ion Popescu'),
            new Credentials($username, 'api-password'),
            $this->http,
            $this->tokens,
            $logger,
            $lookup,
            $this->clock,
        );
    }

    protected function continut(mixed ...$overrides): ContinutContract
    {
        return new ContinutContract(...$overrides + [
            'referintaSalariat' => self::SALARIAT_ID,
            'cor' => new Cor(251204, 10),
            'dataContract' => Dates::date('2026-02-20'),
            'dataInceputContract' => Dates::date('2026-03-01'),
            'numarContract' => '12345',
            'salariu' => 5000,
            'timpMunca' => new TimpMunca(
                norma: 'NormaIntreaga840',
                repartizare: 'OreDeZi',
                durata: 8,
                intervalTimp: 'OrePeZi',
                repartizareMunca: 'Inegal',
            ),
            'tipContract' => 'ContractIndividualMunca',
            'tipDurata' => 'Nedeterminata',
            'tipNorma' => 'NormaIntreaga',
            'tipLocMunca' => 'Mobil',
            'judetLocMunca' => 'CJ',
            'nivelStudii' => 'S',
        ]);
    }

    protected function info(mixed ...$overrides): InfoSalariat
    {
        return new InfoSalariat(...$overrides + [
            'cnp' => '1800612015459',
            'nume' => 'POPESCU',
            'prenume' => 'ION',
            'adresa' => 'STR. SALARIATULUI, NR. 1',
            'tipActIdentitate' => 'CarteIdentitate',
            'taraDomiciliu' => 'ROMÂNIA',
            'nationalitate' => 'ROMÂNIA',
        ]);
    }
}
