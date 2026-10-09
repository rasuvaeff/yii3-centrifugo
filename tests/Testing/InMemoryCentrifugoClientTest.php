<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Testing;

use Rasuvaeff\Yii3Centrifugo\BatchCommand;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClientInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoTransportException;
use Rasuvaeff\Yii3Centrifugo\PublishOptions;
use Rasuvaeff\Yii3Centrifugo\Testing\InMemoryCentrifugoClient;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(InMemoryCentrifugoClient::class)]
final class InMemoryCentrifugoClientTest
{
    public function implementsTheClientInterface(): void
    {
        Assert::instanceOf(new InMemoryCentrifugoClient(), CentrifugoClientInterface::class);
    }

    public function recordsPublishAndBroadcastPerChannel(): void
    {
        $client = new InMemoryCentrifugoClient();

        Assert::same($client->publish(channel: 'news', data: ['id' => 1]), []);
        Assert::same($client->broadcast(channels: ['a', 'b'], data: 'x'), []);

        Assert::same($client->published(), [
            ['channel' => 'news', 'data' => ['id' => 1]],
            ['channel' => 'a', 'data' => 'x'],
            ['channel' => 'b', 'data' => 'x'],
        ]);
        Assert::same($client->publishedTo('b'), ['x']);
        Assert::same($client->publishedTo('news'), [['id' => 1]]);
        Assert::same($client->publishedTo('missing'), []);
    }

    public function recordsPublishOptions(): void
    {
        $client = new InMemoryCentrifugoClient();
        $options = new PublishOptions(idempotencyKey: 'k');

        $client->publish(channel: 'a', data: 1, options: $options);
        $client->broadcast(channels: ['b'], data: 2);

        Assert::same($client->calls(), [
            ['method' => 'publish', 'params' => ['channel' => 'a', 'data' => 1, 'options' => $options]],
            ['method' => 'broadcast', 'params' => ['channels' => ['b'], 'data' => 2, 'options' => null]],
        ]);
    }

    public function publishedIgnoresOtherMethods(): void
    {
        $client = new InMemoryCentrifugoClient();
        $client->subscribe(user: '42', channel: 'news');

        Assert::same($client->published(), []);
    }

    public function recordsEveryMethodWithApiParameterNames(): void
    {
        $client = new InMemoryCentrifugoClient();
        $command = new BatchCommand(method: 'publish', params: ['channel' => 'a', 'data' => []]);

        $client->subscribe(user: '42', channel: 'c');
        $client->unsubscribe(user: '42', channel: 'c');
        $client->disconnect(user: '42', client: 'id', whitelist: true);
        $client->refresh(user: '42', client: 'id', expireAt: 100);
        $client->presence('c');
        $client->presenceStats('c');
        $client->history(channel: 'c', limit: 5, reverse: true, since: ['offset' => 1]);
        $client->historyRemove('c');
        $client->channels('c*');
        $client->info();
        $client->batch($command);

        Assert::same($client->calls(), [
            ['method' => 'subscribe', 'params' => ['user' => '42', 'channel' => 'c']],
            ['method' => 'unsubscribe', 'params' => ['user' => '42', 'channel' => 'c']],
            ['method' => 'disconnect', 'params' => ['user' => '42', 'client' => 'id', 'whitelist' => true]],
            ['method' => 'refresh', 'params' => ['user' => '42', 'client' => 'id', 'expire_at' => 100]],
            ['method' => 'presence', 'params' => ['channel' => 'c']],
            ['method' => 'presence_stats', 'params' => ['channel' => 'c']],
            ['method' => 'history', 'params' => ['channel' => 'c', 'limit' => 5, 'reverse' => true, 'since' => ['offset' => 1]]],
            ['method' => 'history_remove', 'params' => ['channel' => 'c']],
            ['method' => 'channels', 'params' => ['pattern' => 'c*']],
            ['method' => 'info', 'params' => []],
            ['method' => 'batch', 'params' => ['commands' => [$command]]],
        ]);
    }

    public function batchRecordsCommandsAsAList(): void
    {
        $client = new InMemoryCentrifugoClient();
        $command = new BatchCommand(method: 'info', params: []);

        $client->batch(...['first' => $command]);

        Assert::same($client->calls(), [['method' => 'batch', 'params' => ['commands' => [$command]]]]);
    }

    public function everyMethodReturnsAnEmptyResult(): void
    {
        $client = new InMemoryCentrifugoClient();

        Assert::same($client->subscribe(user: '1', channel: 'c'), []);
        Assert::same($client->unsubscribe(user: '1', channel: 'c'), []);
        Assert::same($client->disconnect('1'), []);
        Assert::same($client->refresh('1'), []);
        Assert::same($client->presence('c'), []);
        Assert::same($client->presenceStats('c'), []);
        Assert::same($client->history('c'), []);
        Assert::same($client->historyRemove('c'), []);
        Assert::same($client->channels(), []);
        Assert::same($client->info(), []);
        Assert::same($client->batch(), []);
    }

    public function failNextWithThrowsOnceAndDoesNotRecord(): void
    {
        $client = new InMemoryCentrifugoClient();
        $error = new CentrifugoTransportException('down');
        $client->failNextWith($error);

        try {
            $client->publish(channel: 'news', data: 1);
            Assert::fail('Expected the queued failure');
        } catch (CentrifugoTransportException $e) {
            Assert::same($e, $error);
        }

        Assert::same($client->calls(), []);

        $client->publish(channel: 'news', data: 2);
        Assert::same($client->publishedTo('news'), [2]);
    }

    public function queuedFailuresAreConsumedInOrder(): void
    {
        $client = new InMemoryCentrifugoClient();
        $first = new \RuntimeException('first');
        $second = new \RuntimeException('second');
        $client->failNextWith($first);
        $client->failNextWith($second);

        foreach ([$first, $second] as $expected) {
            try {
                $client->info();
                Assert::fail('Expected a queued failure');
            } catch (\RuntimeException $e) {
                Assert::same($e, $expected);
            }
        }

        Assert::same($client->info(), []);
    }

    public function resetForgetsCallsAndFailures(): void
    {
        $client = new InMemoryCentrifugoClient();
        $client->publish(channel: 'news', data: 1);
        $client->failNextWith(new \RuntimeException('never thrown'));

        $client->reset();

        Assert::same($client->calls(), []);
        $client->info();
        Assert::same($client->calls(), [['method' => 'info', 'params' => []]]);
    }
}
