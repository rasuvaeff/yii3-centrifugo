<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo;

/**
 * Centrifugo v6 server API. Depend on this interface to swap the HTTP client
 * for `Testing\InMemoryCentrifugoClient` in tests.
 *
 * Every method returns the `result` object of the Centrifugo reply and throws
 * a `CentrifugoException` subclass on failure.
 *
 * @api
 */
interface CentrifugoClientInterface
{
    public function publish(string $channel, mixed $data, ?PublishOptions $options = null): array;

    public function broadcast(array $channels, mixed $data, ?PublishOptions $options = null): array;

    public function subscribe(string $user, string $channel): array;

    public function unsubscribe(string $user, string $channel): array;

    public function disconnect(string $user, string $client = '', bool $whitelist = false): array;

    public function refresh(string $user, string $client = '', ?int $expireAt = null): array;

    public function presence(string $channel): array;

    public function presenceStats(string $channel): array;

    public function history(string $channel, int $limit = 0, bool $reverse = false, array $since = []): array;

    public function historyRemove(string $channel): array;

    public function channels(?string $pattern = null): array;

    public function info(): array;

    public function batch(BatchCommand ...$commands): array;
}
