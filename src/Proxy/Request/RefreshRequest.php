<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Proxy\Request;

/**
 * @api
 */
final readonly class RefreshRequest
{
    public function __construct(
        public string $client,
        public string $transport,
        public string $protocol,
        public string $encoding,
        public string $user,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            client: (string) ($data['client'] ?? ''),
            transport: (string) ($data['transport'] ?? ''),
            protocol: (string) ($data['protocol'] ?? ''),
            encoding: (string) ($data['encoding'] ?? ''),
            user: (string) ($data['user'] ?? ''),
        );
    }
}
