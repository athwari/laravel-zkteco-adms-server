<?php

namespace Athwari\ZktecoAdms\Exceptions;

use RuntimeException;

/**
 * Thrown when the maximum number of registered devices has been reached.
 *
 * Equivalent to Go's ErrMaxDevicesReached.
 */
class DeviceLimitReachedException extends RuntimeException
{
    public function __construct(int $limit)
    {
        parent::__construct("Maximum number of devices reached ({$limit}).");
    }
}
