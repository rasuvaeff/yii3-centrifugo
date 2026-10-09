<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Captor;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Centrifugo\BatchCommand;
use Rasuvaeff\Yii3Centrifugo\CentrifugoApiException;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Rasuvaeff\Yii3Centrifugo\CentrifugoException;
use Rasuvaeff\Yii3Centrifugo\CentrifugoTransportException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(CentrifugoClient::class)]
#[Covers(CentrifugoApiException::class)]
#[Covers(BatchCommand::class)]
#[Covers(CentrifugoTransportException::class)]
final class CentrifugoClientTest
{
    private Psr17Factory $factory;

    /** @var Captor<RequestInterface> */
    private Captor $requests;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function publishSendsCorrectRequest(): void
    {
        $client = $this->makeClient(['result' => []]);

        $client->publish(channel: 'news', data: ['title' => 'Hello']);

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($this->requests->last()->getUri()->getPath(), '/api/publish');
        Assert::same($body['channel'], 'news');
        Assert::same($body['data'], ['title' => 'Hello']);
    }

    public function broadcastSendsCorrectRequest(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->broadcast(channels: ['a', 'b'], data: ['x' => 1]);

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($body['channels'], ['a', 'b']);
        Assert::same($this->requests->last()->getUri()->getPath(), '/api/broadcast');
    }

    public function presenceReturnsResult(): void
    {
        $expected = ['presence' => ['client1' => ['user' => '42']]];
        $client = $this->makeClient(['result' => $expected]);

        $result = $client->presence(channel: 'news');

        Assert::same($result, $expected);
    }

    public function historyWithLimitSendsLimit(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->history(channel: 'news', limit: 10, reverse: true);

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($body['limit'], 10);
        Assert::true($body['reverse']);
    }

    public function channelsWithPatternSendsPattern(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->channels(pattern: 'news*');

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($body['pattern'], 'news*');
    }

    public function batchEncodesCommands(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->batch(
            new BatchCommand(method: 'publish', params: ['channel' => 'a', 'data' => []]),
            new BatchCommand(method: 'publish', params: ['channel' => 'b', 'data' => []]),
        );

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($this->requests->last()->getUri()->getPath(), '/api/batch');
        Assert::count($body['commands'], 2);
        Assert::array($body['commands'][0])->hasKeys('publish');
    }

    public function throwsOnApiError(): void
    {
        $client = $this->makeClient(['error' => ['code' => 100, 'message' => 'not found']]);

        try {
            $client->publish(channel: 'x', data: []);
            Assert::fail('Expected CentrifugoApiException');
        } catch (CentrifugoApiException $e) {
            Assert::string($e->getMessage())->contains('not found');
        }
    }

    public function apiExceptionExposesCode(): void
    {
        $client = $this->makeClient(['error' => ['code' => 101, 'message' => 'err']]);

        try {
            $client->info();
            Assert::fail('Expected CentrifugoApiException');
        } catch (CentrifugoApiException $e) {
            Assert::same($e->getApiCode(), 101);
        }
    }

    public function sendsApiKeyHeader(): void
    {
        $client = $this->makeClient(['result' => []], apiKey: 'secret-key');
        $client->info();

        Assert::same($this->requests->last()->getHeaderLine('X-API-Key'), 'secret-key');
    }

    public function disconnectWithClientSendsClient(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->disconnect(user: '42', client: 'client-id');

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($body['client'], 'client-id');
    }

    public function subscribeSendsUserAndChannel(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->subscribe(user: '42', channel: 'news');

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($this->requests->last()->getUri()->getPath(), '/api/subscribe');
        Assert::same($body['user'], '42');
        Assert::same($body['channel'], 'news');
    }

    public function unsubscribeSendsUserAndChannel(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->unsubscribe(user: '42', channel: 'news');

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($this->requests->last()->getUri()->getPath(), '/api/unsubscribe');
        Assert::same($body['user'], '42');
        Assert::same($body['channel'], 'news');
    }

    public function refreshSendsUser(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->refresh(user: '42');

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($this->requests->last()->getUri()->getPath(), '/api/refresh');
        Assert::same($body['user'], '42');
        Assert::array($body)->doesNotHaveKeys('client');
        Assert::array($body)->doesNotHaveKeys('expire_at');
    }

    public function refreshSendsClientWhenProvided(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->refresh(user: '42', client: 'c1');

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($body['client'], 'c1');
    }

    public function refreshSendsExpireAtWhenProvided(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->refresh(user: '42', expireAt: 9999999);

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($body['expire_at'], 9999999);
    }

    public function presenceStatsSendsChannel(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->presenceStats(channel: 'news');

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($this->requests->last()->getUri()->getPath(), '/api/presence_stats');
        Assert::same($body['channel'], 'news');
    }

    public function historyRemoveSendsChannel(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->historyRemove(channel: 'news');

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($this->requests->last()->getUri()->getPath(), '/api/history_remove');
        Assert::same($body['channel'], 'news');
    }

    public function historyWithSinceSendsSince(): void
    {
        $client = $this->makeClient(['result' => []]);
        $since = ['offset' => 5, 'epoch' => 'abc'];
        $client->history(channel: 'news', since: $since);

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::same($body['since'], $since);
    }

    public function disconnectWithWhitelistSendsWhitelist(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->disconnect(user: '42', whitelist: true);

        $body = json_decode((string) $this->requests->last()->getBody(), associative: true);
        Assert::true($body['whitelist']);
    }

    public function defaultApiCodeIsZero(): void
    {
        $e = new CentrifugoApiException('error');

        Assert::same($e->getApiCode(), 0);
    }

    public function apiErrorWithoutCodeDefaultsToZero(): void
    {
        $client = $this->makeClient(['error' => ['message' => 'err']]);

        try {
            $client->info();
            Assert::fail('Expected CentrifugoApiException');
        } catch (CentrifugoApiException $e) {
            Assert::same($e->getApiCode(), 0);
        }
    }

    public function apiExceptionIsACentrifugoException(): void
    {
        Assert::instanceOf(new CentrifugoApiException('error'), CentrifugoException::class);
    }

    public function clientFailureIsWrappedWithPrevious(): void
    {
        $failure = new class ('connection refused') extends \RuntimeException implements ClientExceptionInterface {};
        $httpClient = Understudy::for(ClientInterface::class);
        when(fn() => $httpClient->sendRequest(Arg::any()))->throws($failure);

        try {
            $this->clientWith($httpClient)->publish(channel: 'x', data: []);
            Assert::fail('Expected CentrifugoTransportException');
        } catch (CentrifugoTransportException $e) {
            Assert::same($e->getPrevious(), $failure);
            Assert::null($e->getStatusCode());
            Assert::same($e->getMessage(), 'Centrifugo API request "publish" failed: connection refused');
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function failingStatuses(): iterable
    {
        yield 'below 2xx' => [199];
        yield 'redirect' => [300];
        yield 'unauthorized' => [401];
        yield 'bad gateway' => [502];
    }

    #[DataProvider('failingStatuses')]
    public function non2xxStatusIsATransportError(int $status): void
    {
        $client = $this->makeClient(['result' => []], status: $status);

        try {
            $client->info();
            Assert::fail('Expected CentrifugoTransportException');
        } catch (CentrifugoTransportException $e) {
            Assert::same($e->getStatusCode(), $status);
            Assert::same($e->getMessage(), sprintf('Centrifugo API request "info" failed with HTTP %d', $status));
            Assert::null($e->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function successfulStatuses(): iterable
    {
        yield 'lower bound' => [200];
        yield 'upper bound' => [299];
    }

    #[DataProvider('successfulStatuses')]
    public function any2xxStatusIsAccepted(int $status): void
    {
        $client = $this->makeClient(['result' => ['version' => '6']], status: $status);

        Assert::same($client->info(), ['version' => '6']);
    }

    public function invalidJsonIsATransportErrorWithPrevious(): void
    {
        $client = $this->makeClientWithRawBody('<html>502 Bad Gateway</html>');

        try {
            $client->info();
            Assert::fail('Expected CentrifugoTransportException');
        } catch (CentrifugoTransportException $e) {
            Assert::instanceOf($e->getPrevious(), \JsonException::class);
            Assert::same($e->getStatusCode(), 200);
            Assert::same($e->getMessage(), 'Centrifugo API request "info" returned a body that is not valid JSON');
        }
    }

    public function nonObjectJsonIsATransportError(): void
    {
        $client = $this->makeClientWithRawBody('"ok"');

        try {
            $client->info();
            Assert::fail('Expected CentrifugoTransportException');
        } catch (CentrifugoTransportException $e) {
            Assert::same($e->getStatusCode(), 200);
            Assert::null($e->getPrevious());
            Assert::same($e->getMessage(), 'Centrifugo API request "info" returned a body that is not a JSON object');
        }
    }

    public function transportExceptionDefaultsToNoStatus(): void
    {
        $e = new CentrifugoTransportException('down');

        Assert::null($e->getStatusCode());
        Assert::same($e->getCode(), 0);
        Assert::instanceOf($e, CentrifugoException::class);
    }

    private function makeClient(array $responseBody, string $apiKey = 'test-key', int $status = 200): CentrifugoClient
    {
        return $this->makeClientWithRawBody(json_encode($responseBody, JSON_THROW_ON_ERROR), $apiKey, $status);
    }

    private function makeClientWithRawBody(string $body, string $apiKey = 'test-key', int $status = 200): CentrifugoClient
    {
        $this->requests = Arg::captor(RequestInterface::class);
        $httpClient = Understudy::for(ClientInterface::class);

        when(fn() => $httpClient->sendRequest($this->requests->capture()))
            ->returns((new Response($status))->withBody($this->factory->createStream($body)));

        return $this->clientWith($httpClient, $apiKey);
    }

    private function clientWith(ClientInterface $httpClient, string $apiKey = 'test-key'): CentrifugoClient
    {
        return new CentrifugoClient(
            httpClient: $httpClient,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
            apiUrl: 'http://localhost:8000',
            apiKey: $apiKey,
        );
    }
}
