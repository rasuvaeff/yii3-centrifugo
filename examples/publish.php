<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Rasuvaeff\Yii3Centrifugo\CentrifugoApiException;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClient;
use Rasuvaeff\Yii3Centrifugo\CentrifugoException;
use Rasuvaeff\Yii3Centrifugo\CentrifugoTransportException;
use Rasuvaeff\Yii3Centrifugo\PublishOptions;

// Any PSR-18 client works (Guzzle, symfony/http-client); this minimal one keeps
// the example free of extra dependencies and bounds the call with a timeout.
$psr17 = new Psr17Factory();
$httpClient = new class ($psr17) implements ClientInterface {
    public function __construct(private readonly Psr17Factory $factory) {}

    #[\Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name . ': ' . implode(', ', $values);
        }

        $context = stream_context_create(['http' => [
            'method' => $request->getMethod(),
            'header' => implode("\r\n", $headers),
            'content' => (string) $request->getBody(),
            'timeout' => 2.0,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents((string) $request->getUri(), context: $context);

        if ($body === false) {
            throw new class ('Cannot reach ' . $request->getUri()) extends \RuntimeException implements ClientExceptionInterface {};
        }

        /** @var list<string> $http_response_header */
        $status = (int) (explode(' ', $http_response_header[0] ?? 'HTTP/1.1 502')[1] ?? 502);

        return $this->factory->createResponse($status)->withBody($this->factory->createStream($body));
    }
};

$client = new CentrifugoClient(
    httpClient: $httpClient,
    requestFactory: $psr17,
    streamFactory: $psr17,
    apiUrl: getenv('CENTRIFUGO_API_URL') ?: 'http://localhost:8000',
    apiKey: getenv('CENTRIFUGO_API_KEY') ?: '',
);

try {
    // A retried publish with the same idempotency key is dropped by Centrifugo.
    $result = $client->publish(
        channel: 'news',
        data: ['title' => 'Hello from yii3-centrifugo'],
        options: new PublishOptions(idempotencyKey: 'news-hello-1', tags: ['source' => 'example']),
    );
    echo 'Published, offset/epoch: ' . json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (CentrifugoApiException $e) {
    echo 'Centrifugo refused the publish (code ' . $e->getApiCode() . '): ' . $e->getMessage() . PHP_EOL;
} catch (CentrifugoTransportException $e) {
    echo 'Centrifugo is unreachable or misconfigured (HTTP ' . ($e->getStatusCode() ?? 'none') . '): ' . $e->getMessage() . PHP_EOL;
} catch (CentrifugoException $e) {
    echo 'Centrifugo error: ' . $e->getMessage() . PHP_EOL;
}
