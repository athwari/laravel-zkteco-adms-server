<?php

namespace Athwari\ZktecoAdms\Tests\Feature;

use Athwari\ZktecoAdms\Services\CommandManager;
use Athwari\ZktecoAdms\Services\DeviceManager;
use Athwari\ZktecoAdms\Tests\TestCase;

class GetRequestEndpointTest extends TestCase
{
    public function test_getrequest_returns_ok_when_no_commands(): void
    {
        $response = $this->get('/iclock/getrequest?SN=TEST001');
        $response->assertStatus(200);
        $response->assertSee('OK');
    }

    public function test_getrequest_returns_pending_commands(): void
    {
        // Register device and queue commands
        $deviceManager = app(DeviceManager::class);
        $commandManager = app(CommandManager::class);

        $deviceManager->registerDevice('TEST001');
        $commandManager->queueCommand('TEST001', 'INFO');
        $commandManager->queueCommand('TEST001', 'CHECK');

        $response = $this->get('/iclock/getrequest?SN=TEST001');

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('CHECK', $content);
        $this->assertMatchesRegularExpression('/C:\d+:INFO/', $content);
    }

    public function test_getrequest_drains_commands(): void
    {
        $deviceManager = app(DeviceManager::class);
        $commandManager = app(CommandManager::class);

        $deviceManager->registerDevice('TEST001');
        $commandManager->queueCommand('TEST001', 'INFO');

        // First call drains
        $response1 = $this->get('/iclock/getrequest?SN=TEST001');
        $this->assertStringContainsString('INFO', $response1->getContent());

        // Second call returns OK (no more pending)
        $response2 = $this->get('/iclock/getrequest?SN=TEST001');
        $this->assertEquals('OK', $response2->getContent());
    }

    public function test_getrequest_missing_sn(): void
    {
        $response = $this->get('/iclock/getrequest');
        $response->assertStatus(400);
    }
}
