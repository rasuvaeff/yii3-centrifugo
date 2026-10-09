<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo;

/**
 * Optional fields of the Centrifugo v6 `publish` / `broadcast` API request.
 * Only fields that differ from the defaults are sent.
 *
 * @api
 */
final readonly class PublishOptions
{
    /**
     * @param string|null $idempotencyKey drops a retried duplicate within
     *     Centrifugo's idempotency window (Memory and Redis engines)
     * @param array<string, string> $tags publication tags delivered to subscribers
     * @param int|null $version publication version (Centrifugo 6.2+, channels
     *     with history): a version not above the last seen one is ignored
     * @param string|null $versionEpoch a changed epoch lets a lower version through
     */
    public function __construct(
        public ?string $idempotencyKey = null,
        public bool $skipHistory = false,
        public array $tags = [],
        public bool $delta = false,
        public ?int $version = null,
        public ?string $versionEpoch = null,
    ) {
        if ($idempotencyKey === '') {
            throw new \InvalidArgumentException('idempotencyKey must not be empty');
        }

        $this->assertStringValues($tags);

        if ($version !== null && $version < 1) {
            throw new \InvalidArgumentException('version must be positive');
        }

        if ($versionEpoch === '') {
            throw new \InvalidArgumentException('versionEpoch must not be empty');
        }
    }

    /**
     * Request fields in Centrifugo API names; defaults are omitted. `tags` is
     * an object so that it always encodes as a JSON object.
     *
     * @return array{idempotency_key?: string, skip_history?: true, tags?: object, delta?: true, version?: int, version_epoch?: string}
     */
    public function toPayload(): array
    {
        $payload = [];

        if ($this->idempotencyKey !== null) {
            $payload['idempotency_key'] = $this->idempotencyKey;
        }

        if ($this->skipHistory) {
            $payload['skip_history'] = true;
        }

        if ($this->tags !== []) {
            $payload['tags'] = (object) $this->tags;
        }

        if ($this->delta) {
            $payload['delta'] = true;
        }

        if ($this->version !== null) {
            $payload['version'] = $this->version;
        }

        if ($this->versionEpoch !== null) {
            $payload['version_epoch'] = $this->versionEpoch;
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed> $tags
     */
    private function assertStringValues(array $tags): void
    {
        foreach ($tags as $name => $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException(sprintf('Tag "%s" must be a string, %s given', $name, get_debug_type($value)));
            }
        }
    }
}
