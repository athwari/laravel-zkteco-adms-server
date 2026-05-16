<?php

namespace Athwari\ZktecoAdms\Services;

use Athwari\ZktecoAdms\DTOs\DeviceSnapshot;
use Athwari\ZktecoAdms\Exceptions\DeviceLimitReachedException;
use Athwari\ZktecoAdms\Exceptions\DeviceNotFoundException;
use Athwari\ZktecoAdms\Exceptions\InvalidSerialNumberException;
use Athwari\ZktecoAdms\Models\ZktecoDevice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DeviceManager
{
    private AttendanceParser $parser;

    public function __construct(AttendanceParser $parser)
    {
        $this->parser = $parser;
    }

    public function registerDevice(string $serialNumber): ZktecoDevice
    {
        if (! $this->parser->validateSerialNumber($serialNumber)) {
            throw new InvalidSerialNumberException($serialNumber, 'contains invalid characters');
        }

        $device = ZktecoDevice::where('serial_number', $serialNumber)->first();

        if ($device === null) {
            $maxDevices = config('zkteco-adms.max_devices', 1000);
            if ($maxDevices > 0 && ZktecoDevice::count() >= $maxDevices) {
                throw new DeviceLimitReachedException($maxDevices);
            }

            $device = ZktecoDevice::create([
                'serial_number' => $serialNumber,
                'last_activity' => now(),
                'options' => [],
            ]);

            Log::info('Device registered', ['device' => $serialNumber]);
        }

        return $device;
    }

    public function updateActivity(string $serialNumber): void
    {
        $device = ZktecoDevice::where('serial_number', $serialNumber)->first();
        if ($device === null) {
            return;
        }

        $wasOnline = $this->isOnline($serialNumber);
        $device->update(['last_activity' => now()]);

        if (! $wasOnline) {
            Log::info('Device online', ['device' => $serialNumber]);
        } else {
            Log::debug('Device activity', ['device' => $serialNumber]);
        }
    }

    public function getDevice(string $serialNumber): ?ZktecoDevice
    {
        return ZktecoDevice::where('serial_number', $serialNumber)->first();
    }

    public function deviceExists(string $serialNumber): bool
    {
        return ZktecoDevice::where('serial_number', $serialNumber)->exists();
    }

    public function isOnline(string $serialNumber): bool
    {
        $device = ZktecoDevice::where('serial_number', $serialNumber)->first();
        if ($device === null || $device->last_activity === null) {
            return false;
        }
        $threshold = config('zkteco-adms.online_threshold', 120);

        return $device->last_activity->diffInSeconds(now()) <= $threshold;
    }

    public function listDevices(): Collection
    {
        return ZktecoDevice::all();
    }

    public function setDeviceTimezone(string $serialNumber, string $timezone): void
    {
        $device = ZktecoDevice::where('serial_number', $serialNumber)->first();
        if ($device === null) {
            throw new DeviceNotFoundException($serialNumber);
        }
        $device->update(['timezone' => $timezone]);
    }

    public function getDeviceTimezone(string $serialNumber): string
    {
        $device = ZktecoDevice::where('serial_number', $serialNumber)->first();
        if ($device !== null && $device->timezone !== null) {
            return $device->timezone;
        }

        return config('zkteco-adms.default_timezone', 'UTC');
    }

    public function updateDeviceOptions(string $serialNumber, array $options): void
    {
        $device = ZktecoDevice::where('serial_number', $serialNumber)->first();
        if ($device === null) {
            return;
        }
        $currentOptions = $device->options ?? [];
        $device->update(['options' => array_merge($currentOptions, $options)]);
    }

    public function evictStaleDevices(): int
    {
        $timeout = config('zkteco-adms.device_eviction_timeout', 86400);
        $cutoff = Carbon::now()->subSeconds($timeout);

        $count = ZktecoDevice::where('last_activity', '<', $cutoff)
            ->orWhereNull('last_activity')
            ->count();

        if ($count > 0) {
            ZktecoDevice::where('last_activity', '<', $cutoff)
                ->orWhereNull('last_activity')
                ->delete();
            Log::info('Evicted stale devices', ['count' => $count]);
        }

        return $count;
    }

    public function getDeviceSnapshots(): array
    {
        $devices = ZktecoDevice::all();
        $commandManager = app(CommandManager::class);
        $snapshots = [];

        foreach ($devices as $device) {
            $snapshots[] = new DeviceSnapshot(
                serial: $device->serial_number,
                lastActivity: $device->last_activity?->toIso8601String() ?? '',
                online: $this->isOnline($device->serial_number),
                options: $device->options ?? [],
                timezone: $device->getEffectiveTimezone(),
                pendingCommands: $commandManager->pendingCount($device->serial_number),
            );
        }

        return $snapshots;
    }
}
