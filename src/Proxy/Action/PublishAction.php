<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rasuvaeff\Yii3Centrifugo\Proxy\Handler\PublishProxyHandler;
use Rasuvaeff\Yii3Centrifugo\Proxy\Internal\ProxyResponseFactory;
use Rasuvaeff\Yii3Centrifugo\Proxy\Request\PublishRequest;

/**
 * @api
 */
final readonly class PublishAction implements RequestHandlerInterface
{
    public function __construct(
        private PublishProxyHandler $handler,
        private ProxyResponseFactory $responseFactory,
    ) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = ProxyResponseFactory::parseBody($request);
        $result = $this->handler->handle(PublishRequest::fromArray($body));

        return $this->responseFactory->create($result);
    }
}
