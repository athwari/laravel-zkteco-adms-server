<?php

namespace Athwari\ZktecoAdms\Events;

use Athwari\ZktecoAdms\DTOs\UserRecord;
use Illuminate\Foundation\Events\Dispatchable;

class UserQueryReceived
{
    use Dispatchable;

    /** @param UserRecord[] $users */
    public function __construct(
        public readonly string $serialNumber,
        public readonly array $users,
    ) {}
}
