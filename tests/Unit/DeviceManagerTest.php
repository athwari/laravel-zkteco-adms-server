<?php

use Athwari\ZktecoAdms\Exceptions\DeviceLimitReachedException;
use Athwari\ZktecoAdms\Exceptions\DeviceNotFoundException;
use Athwari\ZktecoAdms\Exceptions\InvalidSerialNumberException;
use Athwari\ZktecoAdms\Services\DeviceManager;

beforeEach(function () {
    $this->manager = app(DeviceManager::class);
});

test('register new device', function () {
    $device = $this->manager->registerDevice('TEST001');

    expect($device->serial_number)->toBe('TEST001')
        ->and($device->last_activity)->not->toBeNull();
});

test('register existing device returns same', function () {
    $first = $this->manager->registerDevice('TEST001');
    $second = $this->manager->registerDevice('TEST001');

    expect($second->id)->toBe($first->id);
});

test('reject invalid serial number', function () {
    $this->manager->registerDevice('has space');
})->throws(InvalidSerialNumberException::class);

test('reject empty serial number', function () {
    $this->manager->registerDevice('');
})->throws(InvalidSerialNumberException::class);

test('device limit enforced', function () {
    config()->set('zkteco-adms.max_devices', 2);

    $this->manager->registerDevice('DEV001');
    $this->manager->registerDevice('DEV002');

    $this->manager->registerDevice('DEV003');
})->throws(DeviceLimitReachedException::class);

test('unlimited devices when zero', function () {
    config()->set('zkteco-adms.max_devices', 0);

    for ($i = 1; $i <= 5; $i++) {
        $device = $this->manager->registerDevice("DEV{$i}");
        expect($device)->not->toBeNull();
    }
});

test('is online', function () {
    $this->manager->registerDevice('TEST001');

    expect($this->manager->isOnline('TEST001'))->toBeTrue();
});

test('is offline for unknown device', function () {
    expect($this->manager->isOnline('UNKNOWN'))->toBeFalse();
});

test('list devices', function () {
    $this->manager->registerDevice('DEV001');
    $this->manager->registerDevice('DEV002');

    expect($this->manager->listDevices())->toHaveCount(2);
});

test('set device timezone', function () {
    $this->manager->registerDevice('TEST001');
    $this->manager->setDeviceTimezone('TEST001', 'Europe/Istanbul');

    expect($this->manager->getDeviceTimezone('TEST001'))->toBe('Europe/Istanbul');
});

test('set timezone for unknown device throws', function () {
    $this->manager->setDeviceTimezone('UNKNOWN', 'UTC');
})->throws(DeviceNotFoundException::class);

test('update device options', function () {
    $this->manager->registerDevice('TEST001');
    $this->manager->updateDeviceOptions('TEST001', ['FWVersion' => '1.0']);

    $device = $this->manager->getDevice('TEST001');
    expect($device->options['FWVersion'])->toBe('1.0');

    // Merge test
    $this->manager->updateDeviceOptions('TEST001', ['DeviceName' => 'TestDev']);
    $device = $this->manager->getDevice('TEST001');
    expect($device->options['FWVersion'])->toBe('1.0')
        ->and($device->options['DeviceName'])->toBe('TestDev');
});

test('evict stale devices', function () {
    config()->set('zkteco-adms.device_eviction_timeout', 1);

    $device = $this->manager->registerDevice('STALE001');
    $device->update(['last_activity' => now()->subSeconds(10)]);

    $count = $this->manager->evictStaleDevices();

    expect($count)->toBe(1)
        ->and($this->manager->getDevice('STALE001'))->toBeNull();
});

test('device snapshots', function () {
    $this->manager->registerDevice('TEST001');
    $snapshots = $this->manager->getDeviceSnapshots();

    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0]->serial)->toBe('TEST001')
        ->and($snapshots[0]->online)->toBeTrue();
});
