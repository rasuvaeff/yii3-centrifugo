<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\SubRefreshProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\SubRefreshRequest;

/**
 * @api
 */
final readonly class SubRefreshAction implements RequestHandlerInterface
{
    public function __construct(
        private SubRefreshProxyHandler $handler,
        private ProxyResponseFactory $responseFactory,
    ) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = ProxyResponseFactory::parseBody($request);
        $result = $this->handler->handle(SubRefreshRequest::fromArray($body));

        return $this->responseFactory->create($result);
    }
}
