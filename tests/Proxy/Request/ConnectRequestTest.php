<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Request;

use Rasuvaeff\Yii3Centrifugo\Proxy\Request\ConnectRequest;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ConnectRequest::class)]
final class ConnectRequestTest
{
    public function fromArrayMapsAllFields(): void
    {
        $req = ConnectRequest::fromArray([
            'client' => 'c1',
            'transport' => 'websocket',
            'protocol' => 'json',
            'encoding' => 'json',
            'data' => ['key' => 'val'],
            'name' => 'myapp',
            'version' => '2.0',
            'channels' => ['news', 'chat'],
        ]);

        Assert::same($req->client, 'c1');
        Assert::same($req->transport, 'websocket');
        Assert::same($req->protocol, 'json');
        Assert::same($req->encoding, 'json');
        Assert::same($req->data, ['key' => 'val']);
        Assert::same($req->name, 'myapp');
        Assert::same($req->version, '2.0');
        Assert::same($req->channels, ['news', 'chat']);
    }

    public function fromArrayCastsNumericsToString(): void
    {
        $req = ConnectRequest::fromArray([
            'client' => 42,
            'transport' => 1,
            'protocol' => 2,
            'encoding' => 3,
        ]);

        Assert::same($req->client, '42');
        Assert::same($req->transport, '1');
        Assert::same($req->protocol, '2');
        Assert::same($req->encoding, '3');
    }

    public function fromArrayDefaultsMissingFields(): void
    {
        $req = ConnectRequest::fromArray([]);

        Assert::same($req->client, '');
        Assert::same($req->transport, '');
        Assert::same($req->protocol, '');
        Assert::same($req->encoding, '');
        Assert::null($req->data);
        Assert::null($req->name);
        Assert::null($req->version);
        Assert::same($req->channels, []);
    }
}
