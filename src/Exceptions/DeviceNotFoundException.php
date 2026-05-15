<?php

namespace Athwari\ZktecoAdms\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation targets a device that is not registered.
 *
 * Equivalent to Go's ErrDeviceNotFound.
 */
class DeviceNotFoundException extends RuntimeException
{
    public function __construct(string $serialNumber)
    {
        parent::__construct("Device not found: '{$serialNumber}'.");
    }
}
