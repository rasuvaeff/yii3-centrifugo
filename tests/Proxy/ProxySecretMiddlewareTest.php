<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Tests\Proxy;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3Centrifugo\Proxy\ProxySecretMiddleware;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(ProxySecretMiddleware::class)]
final class ProxySecretMiddlewareTest
{
    private const string SECRET = 'proxy-secret';

    public function matchingSecretReachesTheHandler(): void
    {
        $response = $this->middleware()->process(
            $this->request(['X-Centrifugo-Proxy-Secret' => self::SECRET]),
            $this->handler(),
        );

        Assert::same($response->getStatusCode(), 200);
    }

    public function missingHeaderIsForbiddenWithoutCallingTheHandler(): void
    {
        $handler = Understudy::strict(Understudy::for(RequestHandlerInterface::class));

        $response = $this->middleware()->process($this->request([]), $handler);

        Assert::same($response->getStatusCode(), 403);
    }

    public function wrongSecretIsForbidden(): void
    {
        $response = $this->middleware()->process(
            $this->request(['X-Centrifugo-Proxy-Secret' => 'proxy-secreT']),
            Understudy::strict(Understudy::for(RequestHandlerInterface::class)),
        );

        Assert::same($response->getStatusCode(), 403);
    }

    public function customHeaderIsRead(): void
    {
        $middleware = new ProxySecretMiddleware(responseFactory: new Psr17Factory(), secret: self::SECRET, header: 'X-Shared-Secret');

        $allowed = $middleware->process($this->request(['X-Shared-Secret' => self::SECRET]), $this->handler());
        $denied = $middleware->process(
            $this->request(['X-Centrifugo-Proxy-Secret' => self::SECRET]),
            Understudy::strict(Understudy::for(RequestHandlerInterface::class)),
        );

        Assert::same($allowed->getStatusCode(), 200);
        Assert::same($denied->getStatusCode(), 403);
    }

    public function repeatedHeaderValuesAreNotAccepted(): void
    {
        $request = (new ServerRequest('POST', '/'))
            ->withHeader('X-Centrifugo-Proxy-Secret', self::SECRET)
            ->withAddedHeader('X-Centrifugo-Proxy-Secret', self::SECRET);

        $response = $this->middleware()->process($request, Understudy::strict(Understudy::for(RequestHandlerInterface::class)));

        Assert::same($response->getStatusCode(), 403);
    }

    public function emptySecretIsRefused(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessage('Proxy secret must not be empty');

        new ProxySecretMiddleware(responseFactory: new Psr17Factory(), secret: '');
    }

    public function emptyHeaderNameIsRefused(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessage('Proxy secret header must not be empty');

        new ProxySecretMiddleware(responseFactory: new Psr17Factory(), secret: self::SECRET, header: '');
    }

    /**
     * Whatever arrives in the header, the request reaches the action exactly
     * when the value equals the secret byte for byte.
     */
    #[Property(runs: 300)]
    public function passesExactlyWhenTheHeaderEqualsTheSecret(string $value): void
    {
        $equal = $value === self::SECRET;
        Classify::cover($equal, 'matching', 10.0);
        Classify::cover(!$equal, 'other', 50.0);

        $response = $this->middleware()->process(
            $this->request(['X-Centrifugo-Proxy-Secret' => $value]),
            $this->handler(),
        );

        Assert::same($response->getStatusCode(), $equal ? 200 : 403);
    }

    /**
     * @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface>
     */
    public static function passesExactlyWhenTheHeaderEqualsTheSecretGenerators(): array
    {
        return [
            'value' => Gen::frequency([
                [1, Gen::oneOf(self::SECRET)],
                [2, Gen::stringFrom('proxy-secretPROXYSECRET ', minLength: 0, maxLength: 16)],
                [2, Gen::map(Gen::stringFrom('proxy-secret', minLength: 1, maxLength: 12), static fn(string $s): string => substr(self::SECRET, 0, strlen($s)))],
            ]),
        ];
    }

    private function middleware(): ProxySecretMiddleware
    {
        return new ProxySecretMiddleware(responseFactory: new Psr17Factory(), secret: self::SECRET);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(array $headers): ServerRequestInterface
    {
        return new ServerRequest('POST', '/centrifugo/connect', $headers);
    }

    private function handler(): RequestHandlerInterface
    {
        $handler = Understudy::for(RequestHandlerInterface::class);
        when(fn() => $handler->handle(Arg::any()))->returns((new Psr17Factory())->createResponse(200));

        return $handler;
    }
}
