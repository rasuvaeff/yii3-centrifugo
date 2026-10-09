<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Testing;

use Rasuvaeff\Yii3Centrifugo\BatchCommand;
use Rasuvaeff\Yii3Centrifugo\CentrifugoClientInterface;
use Rasuvaeff\Yii3Centrifugo\PublishOptions;

/**
 * Test double for `CentrifugoClientInterface`: records every call in memory
 * and talks to no server. Every method returns an empty result.
 *
 * A failure queued with `failNextWith()` is thrown by the next call of any
 * method; that call is not recorded.
 *
 * @api
 */
final class InMemoryCentrifugoClient implements CentrifugoClientInterface
{
    /** @var list<array{method: string, params: array<string, mixed>}> */
    private array $calls = [];

    /** @var list<\Throwable> */
    private array $failures = [];

    /**
     * Every recorded call in order, with the parameters named as in the
     * Centrifugo API (`publish` → `channel`, `data`); `publish` and
     * `broadcast` also carry the `options` object (`PublishOptions|null`).
     *
     * @return list<array{method: string, params: array<string, mixed>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * Everything delivered to channels by `publish()` and `broadcast()`, in
     * order; a broadcast yields one entry per channel.
     *
     * @return list<array{channel: string, data: mixed}>
     */
    public function published(): array
    {
        $published = [];

        foreach ($this->calls as $call) {
            if ($call['method'] === 'publish') {
                /** @var string $channel */
                $channel = $call['params']['channel'];
                $published[] = ['channel' => $channel, 'data' => $call['params']['data']];
            }

            if ($call['method'] === 'broadcast') {
                /** @var array<array-key, string> $channels */
                $channels = $call['params']['channels'];

                foreach ($channels as $channel) {
                    $published[] = ['channel' => $channel, 'data' => $call['params']['data']];
                }
            }
        }

        return $published;
    }

    /**
     * Data delivered to one channel, in order.
     *
     * @return list<mixed>
     */
    public function publishedTo(string $channel): array
    {
        return array_values(array_map(
            static fn(array $publication): mixed => $publication['data'],
            array_filter(
                $this->published(),
                static fn(array $publication): bool => $publication['channel'] === $channel,
            ),
        ));
    }

    /**
     * Makes the next call (of any method) throw `$error`. Queued failures are
     * consumed one per call.
     */
    public function failNextWith(\Throwable $error): void
    {
        $this->failures[] = $error;
    }

    /**
     * Forgets recorded calls and queued failures.
     */
    public function reset(): void
    {
        $this->calls = [];
        $this->failures = [];
    }

    #[\Override]
    public function publish(string $channel, mixed $data, ?PublishOptions $options = null): array
    {
        return $this->record(method: 'publish', params: ['channel' => $channel, 'data' => $data, 'options' => $options]);
    }

    #[\Override]
    public function broadcast(array $channels, mixed $data, ?PublishOptions $options = null): array
    {
        return $this->record(method: 'broadcast', params: ['channels' => $channels, 'data' => $data, 'options' => $options]);
    }

    #[\Override]
    public function subscribe(string $user, string $channel): array
    {
        return $this->record(method: 'subscribe', params: ['user' => $user, 'channel' => $channel]);
    }

    #[\Override]
    public function unsubscribe(string $user, string $channel): array
    {
        return $this->record(method: 'unsubscribe', params: ['user' => $user, 'channel' => $channel]);
    }

    #[\Override]
    public function disconnect(string $user, string $client = '', bool $whitelist = false): array
    {
        return $this->record(method: 'disconnect', params: ['user' => $user, 'client' => $client, 'whitelist' => $whitelist]);
    }

    #[\Override]
    public function refresh(string $user, string $client = '', ?int $expireAt = null): array
    {
        return $this->record(method: 'refresh', params: ['user' => $user, 'client' => $client, 'expire_at' => $expireAt]);
    }

    #[\Override]
    public function presence(string $channel): array
    {
        return $this->record(method: 'presence', params: ['channel' => $channel]);
    }

    #[\Override]
    public function presenceStats(string $channel): array
    {
        return $this->record(method: 'presence_stats', params: ['channel' => $channel]);
    }

    #[\Override]
    public function history(string $channel, int $limit = 0, bool $reverse = false, array $since = []): array
    {
        return $this->record(
            method: 'history',
            params: ['channel' => $channel, 'limit' => $limit, 'reverse' => $reverse, 'since' => $since],
        );
    }

    #[\Override]
    public function historyRemove(string $channel): array
    {
        return $this->record(method: 'history_remove', params: ['channel' => $channel]);
    }

    #[\Override]
    public function channels(?string $pattern = null): array
    {
        return $this->record(method: 'channels', params: ['pattern' => $pattern]);
    }

    #[\Override]
    public function info(): array
    {
        return $this->record(method: 'info', params: []);
    }

    #[\Override]
    public function batch(BatchCommand ...$commands): array
    {
        return $this->record(method: 'batch', params: ['commands' => array_values($commands)]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function record(string $method, array $params): array
    {
        $failure = array_shift($this->failures);

        if ($failure !== null) {
            throw $failure;
        }

        $this->calls[] = ['method' => $method, 'params' => $params];

        return [];
    }
}
