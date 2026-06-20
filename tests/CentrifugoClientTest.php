<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Rasuvaeff\Yii3Centrifugo\BatchCommand;
use Rasuvaeff\Yii3Centrifugo\CentrifugoApiException;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(CentrifugoClient::class)]
#[Covers(CentrifugoApiException::class)]
#[Covers(BatchCommand::class)]
final class CentrifugoClientTest
{
    private Psr17Factory $factory;
    public ?RequestInterface $lastRequest = null;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function publishSendsCorrectRequest(): void
    {
        $client = $this->makeClient(['result' => []]);

        $client->publish(channel: 'news', data: ['title' => 'Hello']);

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($this->lastRequest?->getUri()->getPath(), '/api/publish');
        Assert::same($body['channel'], 'news');
        Assert::same($body['data'], ['title' => 'Hello']);
    }

    public function broadcastSendsCorrectRequest(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->broadcast(channels: ['a', 'b'], data: ['x' => 1]);

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($body['channels'], ['a', 'b']);
        Assert::same($this->lastRequest?->getUri()->getPath(), '/api/broadcast');
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

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($body['limit'], 10);
        Assert::true($body['reverse']);
    }

    public function channelsWithPatternSendsPattern(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->channels(pattern: 'news*');

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($body['pattern'], 'news*');
    }

    public function batchEncodesCommands(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->batch(
            new BatchCommand(method: 'publish', params: ['channel' => 'a', 'data' => []]),
            new BatchCommand(method: 'publish', params: ['channel' => 'b', 'data' => []]),
        );

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($this->lastRequest?->getUri()->getPath(), '/api/batch');
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

        Assert::same($this->lastRequest?->getHeaderLine('X-API-Key'), 'secret-key');
    }

    public function disconnectWithClientSendsClient(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->disconnect(user: '42', client: 'client-id');

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($body['client'], 'client-id');
    }

    public function subscribeSendsUserAndChannel(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->subscribe(user: '42', channel: 'news');

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($this->lastRequest?->getUri()->getPath(), '/api/subscribe');
        Assert::same($body['user'], '42');
        Assert::same($body['channel'], 'news');
    }

    public function unsubscribeSendsUserAndChannel(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->unsubscribe(user: '42', channel: 'news');

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($this->lastRequest?->getUri()->getPath(), '/api/unsubscribe');
        Assert::same($body['user'], '42');
        Assert::same($body['channel'], 'news');
    }

    public function refreshSendsUser(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->refresh(user: '42');

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($this->lastRequest?->getUri()->getPath(), '/api/refresh');
        Assert::same($body['user'], '42');
        Assert::array($body)->doesNotHaveKeys('client');
        Assert::array($body)->doesNotHaveKeys('expire_at');
    }

    public function refreshSendsClientWhenProvided(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->refresh(user: '42', client: 'c1');

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($body['client'], 'c1');
    }

    public function refreshSendsExpireAtWhenProvided(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->refresh(user: '42', expireAt: 9999999);

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($body['expire_at'], 9999999);
    }

    public function presenceStatsSendsChannel(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->presenceStats(channel: 'news');

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($this->lastRequest?->getUri()->getPath(), '/api/presence_stats');
        Assert::same($body['channel'], 'news');
    }

    public function historyRemoveSendsChannel(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->historyRemove(channel: 'news');

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($this->lastRequest?->getUri()->getPath(), '/api/history_remove');
        Assert::same($body['channel'], 'news');
    }

    public function historyWithSinceSendsSince(): void
    {
        $client = $this->makeClient(['result' => []]);
        $since = ['offset' => 5, 'epoch' => 'abc'];
        $client->history(channel: 'news', since: $since);

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::same($body['since'], $since);
    }

    public function disconnectWithWhitelistSendsWhitelist(): void
    {
        $client = $this->makeClient(['result' => []]);
        $client->disconnect(user: '42', whitelist: true);

        $body = json_decode((string) $this->lastRequest?->getBody(), true);
        Assert::true($body['whitelist']);
    }

    public function defaultApiCodeIsZero(): void
    {
        $e = new CentrifugoApiException('error');

        Assert::same($e->getApiCode(), 0);
    }

    private function makeClient(array $responseBody, string $apiKey = 'test-key'): CentrifugoClient
    {
        $factory = $this->factory;
        $test = $this;

        $httpClient = new class ($responseBody, $factory, $test) implements ClientInterface {
            public function __construct(
                private readonly array $body,
                private readonly Psr17Factory $factory,
                private readonly CentrifugoClientTest $test,
            ) {}

            #[\Override]
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->test->lastRequest = $request;
                $json = json_encode($this->body, JSON_THROW_ON_ERROR);

                return (new Response(200))->withBody($this->factory->createStream($json));
            }
        };

        return new CentrifugoClient(
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            apiUrl: 'http://localhost:8000',
            apiKey: $apiKey,
        );
    }
}
