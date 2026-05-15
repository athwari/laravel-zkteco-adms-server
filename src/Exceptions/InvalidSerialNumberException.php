<?php

namespace Athwari\ZktecoAdms\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a serial number fails validation.
 *
 * Equivalent to Go's ErrInvalidSerialNumber.
 */
class InvalidSerialNumberException extends InvalidArgumentException
{
    public function __construct(string $serialNumber, string $reason = '')
    {
        $message = "Invalid serial number: '{$serialNumber}'";
        if ($reason !== '') {
            $message .= " ({$reason})";
        }
        parent::__construct($message);
    }
}
