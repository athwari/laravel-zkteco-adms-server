<?php

namespace Athwari\ZktecoAdms\Tests\Feature;

use Athwari\ZktecoAdms\Events\CommandResultReceived;
use Athwari\ZktecoAdms\Services\CommandManager;
use Athwari\ZktecoAdms\Services\DeviceManager;
use Athwari\ZktecoAdms\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class DeviceCmdEndpointTest extends TestCase
{
    public function test_devicecmd_processes_result(): void
    {
        Event::fake([CommandResultReceived::class]);

        $deviceManager = app(DeviceManager::class);
        $commandManager = app(CommandManager::class);

        $deviceManager->registerDevice('TEST001');
        $cmdId = $commandManager->sendInfoCommand('TEST001');

        $body = "ID={$cmdId}&Return=0&CMD=INFO";
        $response = $this->call('POST', '/iclock/devicecmd?SN=TEST001', [], [], [], [], $body);

        $response->assertStatus(200);
        $response->assertSee('OK');

        Event::assertDispatched(CommandResultReceived::class, function ($event) use ($cmdId) {
            return $event->result->id === $cmdId
                && $event->result->returnCode === 0
                && $event->result->command === 'INFO';
        });
    }

    public function test_devicecmd_handles_batched_results(): void
    {
        Event::fake([CommandResultReceived::class]);

        $deviceManager = app(DeviceManager::class);
        $commandManager = app(CommandManager::class);

        $deviceManager->registerDevice('TEST001');
        $id1 = $commandManager->sendInfoCommand('TEST001');
        $id2 = $commandManager->sendCheckCommand('TEST001');

        $body = "ID={$id1}&Return=0&CMD=INFO\nID={$id2}&Return=0&CMD=CHECK";
        $response = $this->call('POST', '/iclock/devicecmd?SN=TEST001', [], [], [], [], $body);

        $response->assertStatus(200);
        Event::assertDispatched(CommandResultReceived::class, 2);
    }

    public function test_devicecmd_rejects_get(): void
    {
        $response = $this->get('/iclock/devicecmd?SN=TEST001');
        $response->assertStatus(405);
    }
}
