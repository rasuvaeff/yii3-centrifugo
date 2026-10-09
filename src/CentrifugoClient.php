<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @api
 */
final readonly class CentrifugoClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private string $apiUrl,
        private string $apiKey,
    ) {}

    public function publish(string $channel, mixed $data): array
    {
        return $this->send(method: 'publish', payload: ['channel' => $channel, 'data' => $data]);
    }

    public function broadcast(array $channels, mixed $data): array
    {
        return $this->send(method: 'broadcast', payload: ['channels' => $channels, 'data' => $data]);
    }

    public function subscribe(string $user, string $channel): array
    {
        return $this->send(method: 'subscribe', payload: ['user' => $user, 'channel' => $channel]);
    }

    public function unsubscribe(string $user, string $channel): array
    {
        return $this->send(method: 'unsubscribe', payload: ['user' => $user, 'channel' => $channel]);
    }

    public function disconnect(string $user, string $client = '', bool $whitelist = false): array
    {
        $payload = ['user' => $user];

        if ($client !== '') {
            $payload['client'] = $client;
        }

        if ($whitelist) {
            $payload['whitelist'] = true;
        }

        return $this->send(method: 'disconnect', payload: $payload);
    }

    public function refresh(string $user, string $client = '', ?int $expireAt = null): array
    {
        $payload = ['user' => $user];

        if ($client !== '') {
            $payload['client'] = $client;
        }

        if ($expireAt !== null) {
            $payload['expire_at'] = $expireAt;
        }

        return $this->send(method: 'refresh', payload: $payload);
    }

    public function presence(string $channel): array
    {
        return $this->send(method: 'presence', payload: ['channel' => $channel]);
    }

    public function presenceStats(string $channel): array
    {
        return $this->send(method: 'presence_stats', payload: ['channel' => $channel]);
    }

    public function history(
        string $channel,
        int $limit = 0,
        bool $reverse = false,
        array $since = [],
    ): array {
        $payload = ['channel' => $channel];

        if ($limit > 0) {
            $payload['limit'] = $limit;
        }

        if ($reverse) {
            $payload['reverse'] = true;
        }

        if ($since !== []) {
            $payload['since'] = $since;
        }

        return $this->send(method: 'history', payload: $payload);
    }

    public function historyRemove(string $channel): array
    {
        return $this->send(method: 'history_remove', payload: ['channel' => $channel]);
    }

    public function channels(?string $pattern = null): array
    {
        $payload = [];

        if ($pattern !== null) {
            $payload['pattern'] = $pattern;
        }

        return $this->send(method: 'channels', payload: $payload);
    }

    public function info(): array
    {
        return $this->send(method: 'info', payload: []);
    }

    public function batch(BatchCommand ...$commands): array
    {
        $encoded = array_map(
            static fn(BatchCommand $cmd) => [$cmd->method => $cmd->params],
            $commands,
        );

        return $this->send(method: 'batch', payload: ['commands' => $encoded]);
    }

    private function send(string $method, array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $url = rtrim($this->apiUrl, '/') . '/api/' . $method;

        $request = $this->requestFactory
            ->createRequest('POST', $url)
            ->withHeader('X-API-Key', $this->apiKey)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new CentrifugoTransportException(
                message: sprintf('Centrifugo API request "%s" failed: %s', $method, $e->getMessage()),
                previous: $e,
            );
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status > 299) {
            throw new CentrifugoTransportException(
                message: sprintf('Centrifugo API request "%s" failed with HTTP %d', $method, $status),
                statusCode: $status,
            );
        }

        try {
            $result = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new CentrifugoTransportException(
                message: sprintf('Centrifugo API request "%s" returned a body that is not valid JSON', $method),
                statusCode: $status,
                previous: $e,
            );
        }

        if (!is_array($result)) {
            throw new CentrifugoTransportException(
                message: sprintf('Centrifugo API request "%s" returned a body that is not a JSON object', $method),
                statusCode: $status,
            );
        }

        /** @var array{error?: array{code?: int, message?: string}, result?: array<string, mixed>} $result */
        if (isset($result['error'])) {
            throw new CentrifugoApiException(
                message: $result['error']['message'] ?? 'Unknown Centrifugo API error',
                apiCode: $result['error']['code'] ?? 0,
            );
        }

        return $result['result'] ?? [];
    }
}
