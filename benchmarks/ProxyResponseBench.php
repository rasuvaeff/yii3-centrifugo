<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Benchmarks;

use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;
use Testo\Bench;

final class ProxyResponseBench
{
    #[Bench(
        callables: [
            'error' => [self::class, 'constructError'],
        ],
        calls: 1_000,
        iterations: 10,
    )]
    public static function constructResult(): ProxyResult
    {
        return new ProxyResult(data: ['userId' => 'user-123', 'channels' => ['public']]);
    }

    public static function constructError(): ProxyError
    {
        return new ProxyError(code: 403, message: 'Unauthorized');
    }
}
