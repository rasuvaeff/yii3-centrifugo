<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Rasuvaeff\Yii3Centrifugo\Token\ConnectionTokenIssuer;
use Rasuvaeff\Yii3Centrifugo\Token\SubscriptionTokenIssuer;

$secret = getenv('CENTRIFUGO_SECRET') ?: 'demo-secret-at-least-32-chars-long';

$jwtConfig = Configuration::forSymmetricSigner(
    new Sha256(),
    InMemory::plainText($secret),
);

$connectionIssuer = new ConnectionTokenIssuer(jwtConfig: $jwtConfig, defaultTtl: 3600);
$subscriptionIssuer = new SubscriptionTokenIssuer(jwtConfig: $jwtConfig, defaultTtl: 3600);

$connectionToken = $connectionIssuer->issue(
    userId: '42',
    channels: ['news'],
    info: ['name' => 'Alice'],
);

$subscriptionToken = $subscriptionIssuer->issue(
    userId: '42',
    channel: 'private#42',
    info: ['role' => 'user'],
);

echo 'Connection token:' . PHP_EOL;
echo $connectionToken . PHP_EOL . PHP_EOL;
echo 'Subscription token for private#42:' . PHP_EOL;
echo $subscriptionToken . PHP_EOL;
