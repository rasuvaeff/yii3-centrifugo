<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Centrifugo;

/**
 * A package params value is missing or invalid. Thrown when the DI container
 * builds the service that needs it; the message names the params key and
 * never contains the configured value.
 *
 * @api
 */
final class InvalidConfigException extends \InvalidArgumentException {}
