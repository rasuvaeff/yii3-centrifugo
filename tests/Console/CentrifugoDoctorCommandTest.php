<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Console;

use Psr\Container\ContainerInterface;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClientInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoTransportException;
use Rasuvaeff\Yii3Centrifugo\Console\CentrifugoDoctorCommand;
use Rasuvaeff\Yii3Centrifugo\Doctor\CentrifugoDoctor;
use Rasuvaeff\Yii3Centrifugo\Testing\InMemoryCentrifugoClient;
use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(CentrifugoDoctorCommand::class)]
final class CentrifugoDoctorCommandTest
{
    public function commandIsNamedCentrifugoDoctor(): void
    {
        Assert::same($this->command(new InMemoryCentrifugoClient())->getName(), 'centrifugo:doctor');
    }

    public function healthyRunPrintsEveryCheckAndExitsZero(): void
    {
        $tester = new CommandTester($this->command(new InMemoryCentrifugoClient()));

        $code = $tester->execute([]);

        Assert::same($code, 0);
        Assert::same($tester->getDisplay(), implode(PHP_EOL, [
            '[pass] API params: http://centrifugo:8000',
            '[pass] token params: HS256 secret set, token_ttl 3600 s',
            '[pass] server API: info answered, 0 node(s)',
            '[pass] connection token: issued and verified (HS256)',
        ]) . PHP_EOL);
    }

    public function upstreamFailureExitsFour(): void
    {
        $client = new InMemoryCentrifugoClient();
        $client->failNextWith(new CentrifugoTransportException('down'));
        $tester = new CommandTester($this->command($client));

        $code = $tester->execute([]);

        Assert::same($code, 4);
        Assert::string($tester->getDisplay())->contains('[FAIL] server API: down');
    }

    public function configFailureExitsTwoAndPrintsSkips(): void
    {
        $tester = new CommandTester($this->command(new InMemoryCentrifugoClient(), apiUrl: 'nope'));

        $code = $tester->execute([]);

        Assert::same($code, 2);
        Assert::string($tester->getDisplay())->contains('[skip] server API: API params are invalid');
    }

    private function command(CentrifugoClientInterface $client, string $apiUrl = 'http://centrifugo:8000'): CentrifugoDoctorCommand
    {
        $container = Understudy::for(ContainerInterface::class);
        when(fn() => $container->get(CentrifugoClientInterface::class))->returns($client);

        return new CentrifugoDoctorCommand(new CentrifugoDoctor(
            params: ['rasuvaeff/yii3-centrifugo' => [
                'api_url' => $apiUrl,
                'api_key' => 'k',
                'token_hmac_secret' => str_repeat('s', 32),
                'token_ttl' => 3600,
            ]],
            container: $container,
        ));
    }
}
