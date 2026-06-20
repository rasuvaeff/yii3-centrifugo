<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\RpcProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\RpcRequest;

/**
 * @api
 */
final readonly class RpcAction implements RequestHandlerInterface
{
    public function __construct(
        private RpcProxyHandler $handler,
        private ProxyResponseFactory $responseFactory,
    ) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = ProxyResponseFactory::parseBody($request);
        $result = $this->handler->handle(RpcRequest::fromArray($body));

        return $this->responseFactory->create($result);
    }
}
