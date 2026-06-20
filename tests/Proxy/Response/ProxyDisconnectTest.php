<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Response;

use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(ProxyDisconnect::class)]
final class ProxyDisconnectTest
{
    public function validCodeIsAccepted(): void
    {
        $d = new ProxyDisconnect(code: 4001, reason: 'unauthorized');

        Assert::same($d->code, 4001);
        Assert::same($d->reason, 'unauthorized');
    }

    #[DataProvider('invalidCodeProvider')]
    public function throwsOnInvalidCode(int $code): void
    {
        try {
            new ProxyDisconnect(code: $code, reason: 'x');
            Assert::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('ProxyDisconnect code must be in range 4000-4999');
        }
    }

    public static function invalidCodeProvider(): iterable
    {
        yield 'below range' => [3999];
        yield 'above range' => [5000];
        yield 'zero' => [0];
    }

    public function boundaryCodesAreAccepted(): void
    {
        Assert::same((new ProxyDisconnect(code: 4000, reason: 'x'))->code, 4000);
        Assert::same((new ProxyDisconnect(code: 4999, reason: 'x'))->code, 4999);
    }
}
