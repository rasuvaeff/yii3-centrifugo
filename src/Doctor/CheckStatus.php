<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Doctor;

/**
 * @api
 */
enum CheckStatus: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case Skip = 'skip';
}
