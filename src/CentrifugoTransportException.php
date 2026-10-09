<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo;

/**
 * The server API call did not produce a Centrifugo reply: the PSR-18 client
 * failed, the HTTP status was not 2xx, or the body was not a JSON object.
 *
 * @api
 */
final class CentrifugoTransportException extends CentrifugoException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * HTTP status of the response, or null when no response was received.
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
