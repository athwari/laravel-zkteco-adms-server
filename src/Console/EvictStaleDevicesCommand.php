<?php

namespace Athwari\ZktecoAdms\Console;

use Athwari\ZktecoAdms\Services\DeviceManager;
use Illuminate\Console\Command;

/**
 * Artisan command to evict stale (inactive) ZKTeco devices.
 *
 * This replaces the Go library's background goroutine-based eviction worker.
 * Schedule this command in your application's console kernel:
 *
 *     $schedule->command('zkteco:evict-stale-devices')->everyFiveMinutes();
 */
class EvictStaleDevicesCommand extends Command
{
    protected $signature = 'zkteco:evict-stale-devices';

    protected $description = 'Remove ZKTeco devices that have been inactive longer than the eviction timeout';

    public function handle(DeviceManager $deviceManager): int
    {
        if (! config('zkteco-adms.device_eviction_enabled', true)) {
            $this->info('Device eviction is disabled.');

            return self::SUCCESS;
        }

        $count = $deviceManager->evictStaleDevices();

        if ($count > 0) {
            $this->info("Evicted {$count} stale device(s).");
        } else {
            $this->info('No stale devices to evict.');
        }

        return self::SUCCESS;
    }
}
