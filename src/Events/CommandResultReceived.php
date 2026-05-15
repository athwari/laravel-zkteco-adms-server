<?php

namespace Athwari\ZktecoAdms\Events;

use Athwari\ZktecoAdms\DTOs\CommandResult;
use Illuminate\Foundation\Events\Dispatchable;

class CommandResultReceived
{
    use Dispatchable;

    public function __construct(
        public readonly CommandResult $result,
    ) {}
}
