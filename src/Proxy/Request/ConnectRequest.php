<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Request;

/**
 * @api
 */
final readonly class ConnectRequest
{
    public function __construct(
        public string $client,
        public string $transport,
        public string $protocol,
        public string $encoding,
        public mixed $data = null,
        public ?string $name = null,
        public ?string $version = null,
        public array $channels = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            client: (string) ($data['client'] ?? ''),
            transport: (string) ($data['transport'] ?? ''),
            protocol: (string) ($data['protocol'] ?? ''),
            encoding: (string) ($data['encoding'] ?? ''),
            data: $data['data'] ?? null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            version: isset($data['version']) ? (string) $data['version'] : null,
            channels: (array) ($data['channels'] ?? []),
        );
    }
}
