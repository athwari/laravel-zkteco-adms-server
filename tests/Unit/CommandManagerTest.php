<?php

use Athwari\ZktecoAdms\Exceptions\CommandQueueFullException;
use Athwari\ZktecoAdms\Exceptions\DeviceNotFoundException;
use Athwari\ZktecoAdms\Services\CommandManager;
use Athwari\ZktecoAdms\Services\DeviceManager;

beforeEach(function () {
    $this->commandManager = app(CommandManager::class);
    $this->deviceManager = app(DeviceManager::class);
});

test('queue command', function () {
    $this->deviceManager->registerDevice('TEST001');

    $id = $this->commandManager->queueCommand('TEST001', 'INFO');

    expect($id)->toBeGreaterThan(0)
        ->and($this->commandManager->pendingCount('TEST001'))->toBe(1);
});

test('queue command for unknown device', function () {
    $this->commandManager->queueCommand('UNKNOWN', 'INFO');
})->throws(DeviceNotFoundException::class);

test('queue command limit', function () {
    config()->set('zkteco-adms.max_commands_per_device', 2);
    $this->deviceManager->registerDevice('TEST001');

    $this->commandManager->queueCommand('TEST001', 'INFO');
    $this->commandManager->queueCommand('TEST001', 'CHECK');

    $this->commandManager->queueCommand('TEST001', 'LOG');
})->throws(CommandQueueFullException::class);

test('drain commands', function () {
    $this->deviceManager->registerDevice('TEST001');
    $this->commandManager->queueCommand('TEST001', 'INFO');
    $this->commandManager->queueCommand('TEST001', 'CHECK');

    $entries = $this->commandManager->drainCommands('TEST001');

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->command)->toBe('INFO')
        ->and($entries[1]->command)->toBe('CHECK')
        ->and($entries[0]->toWireFormat())->toMatch('/^C:\d+:INFO\n$/');
});

test('drain commands marks as sent', function () {
    $this->deviceManager->registerDevice('TEST001');
    $this->commandManager->queueCommand('TEST001', 'INFO');

    $this->commandManager->drainCommands('TEST001');

    // No more pending (unsent) commands, but still counts sent ones
    $drained = $this->commandManager->drainCommands('TEST001');

    expect($drained)->toHaveCount(0);
});

test('confirm command', function () {
    $this->deviceManager->registerDevice('TEST001');
    $id = $this->commandManager->queueCommand('TEST001', 'INFO');

    $this->commandManager->confirmCommand($id, 0);

    expect($this->commandManager->getQueuedCommand($id))->toBe('INFO');
});

test('convenience commands', function () {
    $this->deviceManager->registerDevice('TEST001');

    $id = $this->commandManager->sendInfoCommand('TEST001');
    expect($id)->toBeGreaterThan(0)
        ->and($this->commandManager->getQueuedCommand($id))->toBe('INFO');

    $id = $this->commandManager->sendCheckCommand('TEST001');
    expect($this->commandManager->getQueuedCommand($id))->toBe('CHECK');

    $id = $this->commandManager->sendLogCommand('TEST001');
    expect($this->commandManager->getQueuedCommand($id))->toBe('LOG');

    $id = $this->commandManager->sendQueryUsersCommand('TEST001');
    expect($this->commandManager->getQueuedCommand($id))->toBe('DATA QUERY USERINFO');
});

test('send user add command', function () {
    $this->deviceManager->registerDevice('TEST001');

    $id = $this->commandManager->sendUserAddCommand('TEST001', '1001', 'John Doe', 0, '12345');
    $cmd = $this->commandManager->getQueuedCommand($id);

    expect($cmd)->toContain('DATA UPDATE USERINFO')
        ->toContain('PIN=1001')
        ->toContain('Name=John Doe');
});

test('send user delete command', function () {
    $this->deviceManager->registerDevice('TEST001');

    $id = $this->commandManager->sendUserDeleteCommand('TEST001', '1001');
    $cmd = $this->commandManager->getQueuedCommand($id);

    expect($cmd)->toBe('DATA DELETE USERINFO PIN=1001');
});

test('reject command with newlines', function () {
    $this->deviceManager->registerDevice('TEST001');
    $this->commandManager->queueCommand('TEST001', "INJECT\nC:999:EVIL");
})->throws(InvalidArgumentException::class);
