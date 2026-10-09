<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Centrifugo\Console\CentrifugoDoctorCommand;

return [
    // read only by yiisoft/yii-console; without it this entry is inert
    'yiisoft/yii-console' => [
        'commands' => [
            'centrifugo:doctor' => CentrifugoDoctorCommand::class,
        ],
    ],
];
