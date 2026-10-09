<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo;

/**
 * Centrifugo answered with an `error` object; the code is Centrifugo's own.
 *
 * @api
 */
final class CentrifugoApiException extends CentrifugoException
{
    public function __construct(string $message, private readonly int $apiCode = 0)
    {
        parent::__construct($message);
    }

    public function getApiCode(): int
    {
        return $this->apiCode;
    }
}
