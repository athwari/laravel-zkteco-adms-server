<?php

namespace Athwari\ZktecoAdms\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DeviceRegistered
{
    use Dispatchable;

    /** @param array<string, string> $options */
    public function __construct(
        public readonly string $serialNumber,
        public readonly array $options = [],
    ) {}
}
