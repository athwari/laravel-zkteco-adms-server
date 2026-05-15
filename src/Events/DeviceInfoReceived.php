<?php

namespace Athwari\ZktecoAdms\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DeviceInfoReceived
{
    use Dispatchable;

    /** @param array<string, string> $info */
    public function __construct(
        public readonly string $serialNumber,
        public readonly array $info,
    ) {}
}
