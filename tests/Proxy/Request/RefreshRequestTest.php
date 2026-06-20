<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Request;

use Rasuvaeff\Yii3Centrifugo\Proxy\Request\RefreshRequest;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(RefreshRequest::class)]
final class RefreshRequestTest
{
    public function fromArrayMapsAllFields(): void
    {
        $req = RefreshRequest::fromArray([
            'client' => 'c1',
            'transport' => 'websocket',
            'protocol' => 'json',
            'encoding' => 'json',
            'user' => '42',
        ]);

        Assert::same($req->client, 'c1');
        Assert::same($req->transport, 'websocket');
        Assert::same($req->protocol, 'json');
        Assert::same($req->encoding, 'json');
        Assert::same($req->user, '42');
    }

    public function fromArrayCastsNumericsToString(): void
    {
        $req = RefreshRequest::fromArray([
            'client' => 1,
            'transport' => 2,
            'protocol' => 3,
            'encoding' => 4,
            'user' => 42,
        ]);

        Assert::same($req->client, '1');
        Assert::same($req->transport, '2');
        Assert::same($req->protocol, '3');
        Assert::same($req->encoding, '4');
        Assert::same($req->user, '42');
    }

    public function fromArrayDefaultsMissingFields(): void
    {
        $req = RefreshRequest::fromArray([]);

        Assert::same($req->client, '');
        Assert::same($req->transport, '');
        Assert::same($req->protocol, '');
        Assert::same($req->encoding, '');
        Assert::same($req->user, '');
    }
}
