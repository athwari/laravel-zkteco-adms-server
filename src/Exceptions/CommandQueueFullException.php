<?php

namespace Athwari\ZktecoAdms\Exceptions;

use RuntimeException;

/**
 * Thrown when the per-device command queue limit has been reached.
 *
 * Equivalent to Go's ErrCommandQueueFull.
 */
class CommandQueueFullException extends RuntimeException
{
    public function __construct(string $serialNumber, int $limit)
    {
        parent::__construct("Command queue full for device {$serialNumber} (limit: {$limit}).");
    }
}
