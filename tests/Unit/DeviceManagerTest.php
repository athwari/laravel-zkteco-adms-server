<?php

namespace Athwari\ZktecoAdms\Tests\Unit;

use Athwari\ZktecoAdms\Exceptions\DeviceLimitReachedException;
use Athwari\ZktecoAdms\Exceptions\DeviceNotFoundException;
use Athwari\ZktecoAdms\Exceptions\InvalidSerialNumberException;
use Athwari\ZktecoAdms\Services\DeviceManager;
use Athwari\ZktecoAdms\Tests\TestCase;

class DeviceManagerTest extends TestCase
{
    private DeviceManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(DeviceManager::class);
    }

    public function test_register_new_device(): void
    {
        $device = $this->manager->registerDevice('TEST001');

        $this->assertEquals('TEST001', $device->serial_number);
        $this->assertNotNull($device->last_activity);
    }

    public function test_register_existing_device_returns_same(): void
    {
        $first = $this->manager->registerDevice('TEST001');
        $second = $this->manager->registerDevice('TEST001');

        $this->assertEquals($first->id, $second->id);
    }

    public function test_reject_invalid_serial_number(): void
    {
        $this->expectException(InvalidSerialNumberException::class);
        $this->manager->registerDevice('has space');
    }

    public function test_reject_empty_serial_number(): void
    {
        $this->expectException(InvalidSerialNumberException::class);
        $this->manager->registerDevice('');
    }

    public function test_device_limit_enforced(): void
    {
        config()->set('zkteco-adms.max_devices', 2);

        $this->manager->registerDevice('DEV001');
        $this->manager->registerDevice('DEV002');

        $this->expectException(DeviceLimitReachedException::class);
        $this->manager->registerDevice('DEV003');
    }

    public function test_unlimited_devices_when_zero(): void
    {
        config()->set('zkteco-adms.max_devices', 0);

        for ($i = 1; $i <= 5; $i++) {
            $device = $this->manager->registerDevice("DEV{$i}");
            $this->assertNotNull($device);
        }
    }

    public function test_is_online(): void
    {
        $this->manager->registerDevice('TEST001');
        $this->assertTrue($this->manager->isOnline('TEST001'));
    }

    public function test_is_offline_for_unknown_device(): void
    {
        $this->assertFalse($this->manager->isOnline('UNKNOWN'));
    }

    public function test_list_devices(): void
    {
        $this->manager->registerDevice('DEV001');
        $this->manager->registerDevice('DEV002');

        $devices = $this->manager->listDevices();
        $this->assertCount(2, $devices);
    }

    public function test_set_device_timezone(): void
    {
        $this->manager->registerDevice('TEST001');
        $this->manager->setDeviceTimezone('TEST001', 'Europe/Istanbul');

        $tz = $this->manager->getDeviceTimezone('TEST001');
        $this->assertEquals('Europe/Istanbul', $tz);
    }

    public function test_set_timezone_for_unknown_device_throws(): void
    {
        $this->expectException(DeviceNotFoundException::class);
        $this->manager->setDeviceTimezone('UNKNOWN', 'UTC');
    }

    public function test_update_device_options(): void
    {
        $this->manager->registerDevice('TEST001');
        $this->manager->updateDeviceOptions('TEST001', ['FWVersion' => '1.0']);

        $device = $this->manager->getDevice('TEST001');
        $this->assertEquals('1.0', $device->options['FWVersion']);

        // Merge test
        $this->manager->updateDeviceOptions('TEST001', ['DeviceName' => 'TestDev']);
        $device = $this->manager->getDevice('TEST001');
        $this->assertEquals('1.0', $device->options['FWVersion']);
        $this->assertEquals('TestDev', $device->options['DeviceName']);
    }

    public function test_evict_stale_devices(): void
    {
        config()->set('zkteco-adms.device_eviction_timeout', 1);

        $device = $this->manager->registerDevice('STALE001');
        $device->update(['last_activity' => now()->subSeconds(10)]);

        $count = $this->manager->evictStaleDevices();
        $this->assertEquals(1, $count);
        $this->assertNull($this->manager->getDevice('STALE001'));
    }

    public function test_device_snapshots(): void
    {
        $this->manager->registerDevice('TEST001');
        $snapshots = $this->manager->getDeviceSnapshots();

        $this->assertCount(1, $snapshots);
        $this->assertEquals('TEST001', $snapshots[0]->serial);
        $this->assertTrue($snapshots[0]->online);
    }
}
