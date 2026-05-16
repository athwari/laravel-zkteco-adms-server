<?php

namespace Athwari\ZktecoAdms\Facades;

use Athwari\ZktecoAdms\Services\CommandManager;
use Athwari\ZktecoAdms\Services\DeviceManager;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for convenient access to ZKTeco ADMS functionality.
 *
 * Device management methods are proxied to DeviceManager:
 *
 * @method static \Athwari\ZktecoAdms\Models\ZktecoDevice registerDevice(string $serialNumber)
 * @method static void updateActivity(string $serialNumber)
 * @method static \Athwari\ZktecoAdms\Models\ZktecoDevice|null getDevice(string $serialNumber)
 * @method static bool deviceExists(string $serialNumber)
 * @method static bool isOnline(string $serialNumber)
 * @method static \Illuminate\Support\Collection listDevices()
 * @method static void setDeviceTimezone(string $serialNumber, string $timezone)
 * @method static string getDeviceTimezone(string $serialNumber)
 * @method static int evictStaleDevices()
 * @method static array getDeviceSnapshots()
 *
 * @see DeviceManager
 */
class ZktecoAdms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DeviceManager::class;
    }

    /**
     * Get the command manager instance for sending commands to devices.
     */
    public static function commands(): CommandManager
    {
        return app(CommandManager::class);
    }
}
