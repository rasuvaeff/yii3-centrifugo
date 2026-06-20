<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Request;

use Rasuvaeff\Yii3Centrifugo\Proxy\Request\PublishRequest;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(PublishRequest::class)]
final class PublishRequestTest
{
    public function fromArrayMapsAllFields(): void
    {
        $req = PublishRequest::fromArray([
            'client' => 'c1',
            'transport' => 'websocket',
            'protocol' => 'json',
            'encoding' => 'json',
            'user' => '42',
            'channel' => 'news',
            'data' => ['msg' => 'hello'],
        ]);

        Assert::same($req->client, 'c1');
        Assert::same($req->transport, 'websocket');
        Assert::same($req->protocol, 'json');
        Assert::same($req->encoding, 'json');
        Assert::same($req->user, '42');
        Assert::same($req->channel, 'news');
        Assert::same($req->data, ['msg' => 'hello']);
    }

    public function fromArrayCastsNumericsToString(): void
    {
        $req = PublishRequest::fromArray([
            'client' => 1,
            'transport' => 2,
            'protocol' => 3,
            'encoding' => 4,
            'user' => 42,
            'channel' => 99,
        ]);

        Assert::same($req->client, '1');
        Assert::same($req->user, '42');
        Assert::same($req->channel, '99');
    }

    public function fromArrayDefaultsMissingFields(): void
    {
        $req = PublishRequest::fromArray([]);

        Assert::same($req->client, '');
        Assert::same($req->transport, '');
        Assert::same($req->protocol, '');
        Assert::same($req->encoding, '');
        Assert::same($req->user, '');
        Assert::same($req->channel, '');
        Assert::null($req->data);
    }
}
