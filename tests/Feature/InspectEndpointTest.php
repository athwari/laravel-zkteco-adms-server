<?php

namespace Athwari\ZktecoAdms\Tests\Feature;

use Athwari\ZktecoAdms\Services\DeviceManager;
use Athwari\ZktecoAdms\Tests\TestCase;

class InspectEndpointTest extends TestCase
{
    public function test_inspect_disabled_by_default(): void
    {
        $response = $this->get('/iclock/inspect?SN=TEST001');
        $response->assertStatus(404);
    }

    public function test_inspect_returns_json_when_enabled(): void
    {
        config()->set('zkteco-adms.enable_inspect', true);

        $deviceManager = app(DeviceManager::class);
        $deviceManager->registerDevice('TEST001');

        $response = $this->get('/iclock/inspect?SN=TEST001');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('devices', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('time', $data);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals('TEST001', $data['devices'][0]['serial']);
    }
}
