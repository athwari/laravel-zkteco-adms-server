<?php

use Athwari\ZktecoAdms\Events\CommandResultReceived;
use Athwari\ZktecoAdms\Services\CommandManager;
use Athwari\ZktecoAdms\Services\DeviceManager;
use Illuminate\Support\Facades\Event;

test('devicecmd processes result', function () {
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
});

test('devicecmd handles batched results', function () {
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
});

test('devicecmd rejects get', function () {
    $this->get('/iclock/devicecmd?SN=TEST001')->assertStatus(405);
});
