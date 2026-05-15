<?php

namespace Athwari\ZktecoAdms\Events;

use Athwari\ZktecoAdms\DTOs\AttendanceRecord;
use Illuminate\Foundation\Events\Dispatchable;

class AttendanceReceived
{
    use Dispatchable;

    /** @param AttendanceRecord[] $records */
    public function __construct(
        public readonly string $serialNumber,
        public readonly array $records,
    ) {}
}
