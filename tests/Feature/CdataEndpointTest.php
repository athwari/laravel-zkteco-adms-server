<?php

use Athwari\ZktecoAdms\Events\AttendanceReceived;
use Athwari\ZktecoAdms\Events\DeviceInfoReceived;
use Athwari\ZktecoAdms\Models\ZktecoAttendanceLog;
use Illuminate\Support\Facades\Event;

test('cdata get handshake', function () {
    $response = $this->get('/iclock/cdata?SN=TEST001');

    $response->assertStatus(200);
    $response->assertSee('OK');
});

test('cdata missing sn returns 400', function () {
    $this->get('/iclock/cdata')->assertStatus(400);
});

test('cdata invalid sn returns 400', function () {
    $this->get('/iclock/cdata?SN=has%20space')->assertStatus(400);
});

test('cdata post attlog', function () {
    Event::fake([AttendanceReceived::class]);

    $body = "1001\t2024-03-15 08:30:00\t0\t1\t\n1002\t2024-03-15 08:31:00\t1\t4\tWC01";

    $response = $this->call('POST', '/iclock/cdata?SN=TEST001&table=ATTLOG', [], [], [], [], $body);

    $response->assertStatus(200);
    $response->assertSee('OK: 2');

    $table = (new ZktecoAttendanceLog())->getTable();

    $this->assertDatabaseCount($table, 2);
    $this->assertDatabaseHas($table, [
        'user_id' => '1001',
        'device_serial_number' => 'TEST001',
        'status' => 0,
    ]);

    Event::assertDispatched(AttendanceReceived::class, function ($event) {
        return $event->serialNumber === 'TEST001' && count($event->records) === 2;
    });
});

test('cdata post operlog', function () {
    $response = $this->call('POST', '/iclock/cdata?SN=TEST001&table=OPERLOG', [], [], [], [], 'some log data');

    $response->assertStatus(200);
    $response->assertSee('OK');
});

test('cdata post device info', function () {
    Event::fake([DeviceInfoReceived::class]);

    $body = "FWVersion=Ver 8.1.1\nDeviceName=TestDevice\nIPAddress=192.168.1.100";

    $response = $this->call('POST', '/iclock/cdata?SN=TEST001', [], [], [], [], $body);

    $response->assertStatus(200);

    Event::assertDispatched(DeviceInfoReceived::class, function ($event) {
        return $event->serialNumber === 'TEST001'
            && $event->info['FWVersion'] === 'Ver 8.1.1';
    });
});

test('cdata device limit reached', function () {
    config()->set('zkteco-adms.max_devices', 1);

    $this->get('/iclock/cdata?SN=DEV001');

    $this->get('/iclock/cdata?SN=DEV002')->assertStatus(503);
});
