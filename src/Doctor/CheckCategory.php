<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo\Doctor;

/**
 * What a failing check points at; the value is the doctor's exit code.
 *
 * @api
 */
enum CheckCategory: int
{
    case Config = 2;
    case Upstream = 4;
}
