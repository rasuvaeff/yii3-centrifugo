<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Doctor;

use Psr\Container\ContainerInterface;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClientInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoTransportException;
use Rasuvaeff\Yii3Centrifugo\Doctor\CentrifugoDoctor;
use Rasuvaeff\Yii3Centrifugo\Doctor\CheckCategory;
use Rasuvaeff\Yii3Centrifugo\Doctor\CheckResult;
use Rasuvaeff\Yii3Centrifugo\Doctor\CheckStatus;
use Rasuvaeff\Yii3Centrifugo\Doctor\DoctorReport;
use Rasuvaeff\Yii3Centrifugo\Testing\InMemoryCentrifugoClient;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(CentrifugoDoctor::class)]
#[Covers(DoctorReport::class)]
#[Covers(CheckResult::class)]
final class CentrifugoDoctorTest
{
    private const string SECRET = 'doctor-secret-at-least-32-bytes-long';

    public function healthyDeploymentPassesEveryCheck(): void
    {
        $report = $this->diagnose(client: new InMemoryCentrifugoClient());

        Assert::same($this->summary($report), [
            ['API params', 'pass', 'http://centrifugo:8000'],
            ['token params', 'pass', 'HS256 secret set, token_ttl 600 s'],
            ['server API', 'pass', 'info answered, 0 node(s)'],
            ['connection token', 'pass', 'issued and verified (HS256)'],
        ]);
        Assert::true($report->healthy());
        Assert::same($report->exitCode(), 0);
    }

    public function serverApiReportsTheNodeCount(): void
    {
        $client = Understudy::for(CentrifugoClientInterface::class);
        when(fn() => $client->info())->returns(['nodes' => [['uid' => 'a'], ['uid' => 'b']]]);

        $report = $this->diagnose(client: $client);

        Assert::same($report->checks[2]->details, 'info answered, 2 node(s)');
    }

    public function malformedNodeListCountsAsZero(): void
    {
        $client = Understudy::for(CentrifugoClientInterface::class);
        when(fn() => $client->info())->returns(['nodes' => 'unexpected']);

        Assert::same($this->diagnose(client: $client)->checks[2]->details, 'info answered, 0 node(s)');
    }

    public function emptyApiKeyIsFlaggedButPasses(): void
    {
        $report = $this->diagnose(params: ['api_key' => ''], client: new InMemoryCentrifugoClient());

        Assert::same($report->checks[0]->status, CheckStatus::Pass);
        Assert::same($report->checks[0]->details, 'http://centrifugo:8000, empty api_key (Centrifugo must run with api_insecure)');
    }

    public function invalidApiParamsFailAsConfigAndSkipTheServerCall(): void
    {
        $report = $this->diagnose(params: ['api_url' => 'centrifugo:8000']);

        Assert::same($this->summary($report)[0], ['API params', 'fail', "params['rasuvaeff/yii3-centrifugo']['api_url'] must be an http(s) URL"]);
        Assert::same($this->summary($report)[2], ['server API', 'skip', 'API params are invalid']);
        Assert::same($report->checks[2]->category, CheckCategory::Upstream);
        Assert::same($report->exitCode(), 2);
        Assert::false($report->healthy());
    }

    public function invalidTokenParamsFailAsConfigAndSkipTheRoundTrip(): void
    {
        $report = $this->diagnose(params: ['token_hmac_secret' => 'short'], client: new InMemoryCentrifugoClient());

        Assert::same($this->summary($report)[1][1], 'fail');
        Assert::same($this->summary($report)[3], ['connection token', 'skip', 'token params are invalid']);
        Assert::same($report->checks[3]->category, CheckCategory::Config);
        Assert::same($report->exitCode(), 2);
    }

    public function unreachableServerFailsAsUpstream(): void
    {
        $client = new InMemoryCentrifugoClient();
        $client->failNextWith(new CentrifugoTransportException('Centrifugo API request "info" failed with HTTP 401', statusCode: 401));

        $report = $this->diagnose(client: $client);

        Assert::same($this->summary($report)[2], ['server API', 'fail', 'Centrifugo API request "info" failed with HTTP 401']);
        Assert::same($report->checks[2]->category, CheckCategory::Upstream);
        Assert::same($report->exitCode(), 4);
    }

    public function clientThatCannotBeBuiltFailsAsConfig(): void
    {
        $container = Understudy::for(ContainerInterface::class);
        when(fn() => $container->get(CentrifugoClientInterface::class))->throws(new \RuntimeException('no PSR-18 client bound'));

        $report = (new CentrifugoDoctor(params: $this->params(), container: $container))->diagnose();

        Assert::same($this->summary($report)[2], ['server API', 'fail', 'cannot build the client: no PSR-18 client bound']);
        Assert::same($report->checks[2]->category, CheckCategory::Config);
        Assert::same($report->exitCode(), 2);
    }

    public function containerEntryThatIsNotAClientFailsAsConfig(): void
    {
        $container = Understudy::for(ContainerInterface::class);
        when(fn() => $container->get(CentrifugoClientInterface::class))->returns(new \stdClass());

        $report = (new CentrifugoDoctor(params: $this->params(), container: $container))->diagnose();

        Assert::same($this->summary($report)[2], ['server API', 'fail', 'cannot build the client: container returned stdClass']);
        Assert::same($report->exitCode(), 2);
    }

    public function exitCodeIsTheCategoryOfTheFirstFailure(): void
    {
        $client = new InMemoryCentrifugoClient();
        $client->failNextWith(new CentrifugoTransportException('down'));

        // token params (config) fail before the server API (upstream) does
        $report = $this->diagnose(params: ['token_ttl' => 0], client: $client);

        Assert::same($report->checks[2]->status, CheckStatus::Fail);
        Assert::same($report->exitCode(), 2);
    }

    public function upstreamFailureBeforeAConfigFailureGivesFour(): void
    {
        $report = new DoctorReport([
            new CheckResult(name: 'a', category: CheckCategory::Upstream, status: CheckStatus::Fail, details: ''),
            new CheckResult(name: 'b', category: CheckCategory::Config, status: CheckStatus::Fail, details: ''),
        ]);

        Assert::same($report->exitCode(), 4);
    }

    public function skippedChecksDoNotFailTheReport(): void
    {
        $report = new DoctorReport([
            new CheckResult(name: 'a', category: CheckCategory::Upstream, status: CheckStatus::Skip, details: ''),
        ]);

        Assert::true($report->healthy());
        Assert::same($report->exitCode(), 0);
    }

    public function detailsNeverContainTheSecretOrApiKey(): void
    {
        $client = new InMemoryCentrifugoClient();
        $client->failNextWith(new CentrifugoTransportException('down'));

        foreach ([$this->diagnose(client: $client), $this->diagnose(params: ['token_ttl' => 0], client: new InMemoryCentrifugoClient())] as $report) {
            foreach ($report->checks as $check) {
                Assert::false(str_contains($check->details, self::SECRET));
                Assert::false(str_contains($check->details, 'doctor-api-key'));
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function diagnose(array $params = [], ?CentrifugoClientInterface $client = null): DoctorReport
    {
        $container = Understudy::for(ContainerInterface::class);

        if ($client instanceof \Rasuvaeff\Yii3Centrifugo\CentrifugoClientInterface) {
            when(fn() => $container->get(CentrifugoClientInterface::class))->returns($client);
        }

        return (new CentrifugoDoctor(params: $this->params($params), container: $container))->diagnose();
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, array<string, mixed>>
     */
    private function params(array $override = []): array
    {
        return ['rasuvaeff/yii3-centrifugo' => [
            'api_url' => 'http://centrifugo:8000',
            'api_key' => 'doctor-api-key',
            'token_hmac_secret' => self::SECRET,
            'token_ttl' => 600,
            ...$override,
        ]];
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function summary(DoctorReport $report): array
    {
        return array_map(
            static fn(CheckResult $check): array => [$check->name, $check->status->value, $check->details],
            $report->checks,
        );
    }
}
