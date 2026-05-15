<?php

namespace Athwari\ZktecoAdms\Tests\Feature;

use Athwari\ZktecoAdms\Events\AttendanceReceived;
use Athwari\ZktecoAdms\Events\DeviceInfoReceived;
use Athwari\ZktecoAdms\Models\ZktecoAttendanceLog;
use Athwari\ZktecoAdms\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class CdataEndpointTest extends TestCase
{
    public function test_cdata_get_handshake(): void
    {
        $response = $this->get('/iclock/cdata?SN=TEST001');
        $response->assertStatus(200);
        $response->assertSee('OK');
    }

    public function test_cdata_missing_sn_returns_400(): void
    {
        $response = $this->get('/iclock/cdata');
        $response->assertStatus(400);
    }

    public function test_cdata_invalid_sn_returns_400(): void
    {
        $response = $this->get('/iclock/cdata?SN=has%20space');
        $response->assertStatus(400);
    }

    public function test_cdata_post_attlog(): void
    {
        Event::fake([AttendanceReceived::class]);

        $body = "1001\t2024-03-15 08:30:00\t0\t1\t\n1002\t2024-03-15 08:31:00\t1\t4\tWC01";

        $response = $this->call('POST', '/iclock/cdata?SN=TEST001&table=ATTLOG', [], [], [], [], $body);

        $response->assertStatus(200);
        $response->assertSee('OK: 2');

        // Verify database records
        $this->assertDatabaseCount((new ZktecoAttendanceLog)->getTable(), 2);
        $this->assertDatabaseHas((new ZktecoAttendanceLog)->getTable(), [
            'user_id' => '1001',
            'device_serial_number' => 'TEST001',
            'status' => 0,
        ]);

        // Verify event dispatched
        Event::assertDispatched(AttendanceReceived::class, function ($event) {
            return $event->serialNumber === 'TEST001' && count($event->records) === 2;
        });
    }

    public function test_cdata_post_operlog(): void
    {
        $response = $this->call('POST', '/iclock/cdata?SN=TEST001&table=OPERLOG', [], [], [], [], 'some log data');
        $response->assertStatus(200);
        $response->assertSee('OK');
    }

    public function test_cdata_post_device_info(): void
    {
        Event::fake([DeviceInfoReceived::class]);

        $body = "FWVersion=Ver 8.1.1\nDeviceName=TestDevice\nIPAddress=192.168.1.100";

        $response = $this->call('POST', '/iclock/cdata?SN=TEST001', [], [], [], [], $body);

        $response->assertStatus(200);

        Event::assertDispatched(DeviceInfoReceived::class, function ($event) {
            return $event->serialNumber === 'TEST001'
                && $event->info['FWVersion'] === 'Ver 8.1.1';
        });
    }

    public function test_cdata_device_limit_reached(): void
    {
        config()->set('zkteco-adms.max_devices', 1);

        $this->get('/iclock/cdata?SN=DEV001');
        $response = $this->get('/iclock/cdata?SN=DEV002');

        $response->assertStatus(503);
    }
}
