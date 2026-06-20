<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo;

/**
 * @api
 */
final class CentrifugoApiException extends \RuntimeException
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
