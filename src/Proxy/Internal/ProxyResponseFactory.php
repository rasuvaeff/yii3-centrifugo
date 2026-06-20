<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Internal;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyDisconnect;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyError;
use Rasuvaeff\Yii3Centrifugo\Proxy\Response\ProxyResult;

/**
 * @internal
 */
final readonly class ProxyResponseFactory
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function create(ProxyResult|ProxyError|ProxyDisconnect $envelope): ResponseInterface
    {
        $body = match (true) {
            $envelope instanceof ProxyResult => ['result' => $envelope->data],
            $envelope instanceof ProxyError => ['error' => ['code' => $envelope->code, 'message' => $envelope->message]],
            $envelope instanceof ProxyDisconnect => ['disconnect' => ['code' => $envelope->code, 'reason' => $envelope->reason]],
        };

        $json = json_encode($body, JSON_THROW_ON_ERROR);

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($json));
    }

    public static function parseBody(\Psr\Http\Message\ServerRequestInterface $request): array
    {
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $body;
    }
}
