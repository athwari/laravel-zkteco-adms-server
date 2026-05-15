<?php

namespace Athwari\ZktecoAdms\Tests\Feature;

use Athwari\ZktecoAdms\Events\DeviceRegistered;
use Athwari\ZktecoAdms\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class RegistryEndpointTest extends TestCase
{
    public function test_registry_get(): void
    {
        $response = $this->get('/iclock/registry?SN=TEST001');
        $response->assertStatus(200);
        $response->assertSee('OK');
    }

    public function test_registry_post_with_body(): void
    {
        Event::fake([DeviceRegistered::class]);

        $body = '~DeviceName=SpeedFace,~FWVersion=Ver 1.1.17,~MACAddress=AA:BB:CC:DD:EE:FF';
        $response = $this->call('POST', '/iclock/registry?SN=TEST001', [], [], [], [], $body);

        $response->assertStatus(200);
        $response->assertSee('OK');

        Event::assertDispatched(DeviceRegistered::class, function ($event) {
            return $event->serialNumber === 'TEST001'
                && $event->options['DeviceName'] === 'SpeedFace'
                && $event->options['FWVersion'] === 'Ver 1.1.17';
        });
    }

    public function test_registry_updates_device_options(): void
    {
        $body = '~DeviceName=SpeedFace,~FWVersion=Ver 1.0';
        $this->call('POST', '/iclock/registry?SN=TEST001', [], [], [], [], $body);

        $device = app(\Athwari\ZktecoAdms\Services\DeviceManager::class)->getDevice('TEST001');
        $this->assertEquals('SpeedFace', $device->options['DeviceName']);
    }
}
