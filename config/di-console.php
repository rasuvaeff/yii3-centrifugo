<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Rasuvaeff\Yii3Centrifugo\Doctor\CentrifugoDoctor;

return [
    CentrifugoDoctor::class => static fn(ContainerInterface $container): CentrifugoDoctor => new CentrifugoDoctor(
        params: $params,
        container: $container,
    ),
];
