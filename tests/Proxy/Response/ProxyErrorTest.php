<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy\Response;

use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(ProxyError::class)]
final class ProxyErrorTest
{
    public function validCodeIsAccepted(): void
    {
        $error = new ProxyError(code: 403, message: 'permission denied');

        Assert::same($error->code, 403);
        Assert::same($error->message, 'permission denied');
    }

    #[DataProvider('invalidCodeProvider')]
    public function throwsOnInvalidCode(int $code): void
    {
        try {
            new ProxyError(code: $code, message: 'err');
            Assert::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('ProxyError code must be in range 400-1999');
        }
    }

    public static function invalidCodeProvider(): iterable
    {
        yield 'below range' => [399];
        yield 'above range' => [2000];
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    public function boundaryCodesAreAccepted(): void
    {
        Assert::same((new ProxyError(code: 400, message: 'x'))->code, 400);
        Assert::same((new ProxyError(code: 1999, message: 'x'))->code, 1999);
    }
}
