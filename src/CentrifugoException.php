<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo;

/**
 * Base class for every failure of a Centrifugo server API call: catch it to
 * handle "Centrifugo is unavailable or misconfigured" in one place.
 *
 * @api
 */
abstract class CentrifugoException extends \RuntimeException {}
