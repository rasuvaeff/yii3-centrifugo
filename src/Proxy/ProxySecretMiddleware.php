<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lets a proxy request through only when it carries the shared secret that
 * Centrifugo sends via `http.static_headers`. The comparison is
 * constant-time; a missing or wrong secret is answered with HTTP 403 before
 * the proxy action runs, which Centrifugo treats as a proxy failure
 * (`100: internal server error` for the client) — not as a proxy reply.
 *
 * @api
 */
final readonly class ProxySecretMiddleware implements MiddlewareInterface
{
    public const string DEFAULT_HEADER = 'X-Centrifugo-Proxy-Secret';

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        #[\SensitiveParameter]
        private string $secret,
        private string $header = self::DEFAULT_HEADER,
    ) {
        if ($secret === '') {
            throw new \InvalidArgumentException('Proxy secret must not be empty');
        }

        if ($header === '') {
            throw new \InvalidArgumentException('Proxy secret header must not be empty');
        }
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!hash_equals($this->secret, $request->getHeaderLine($this->header))) {
            return $this->responseFactory->createResponse(403);
        }

        return $handler->handle($request);
    }
}
