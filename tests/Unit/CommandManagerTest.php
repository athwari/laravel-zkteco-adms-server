<?php

namespace Athwari\ZktecoAdms\Tests\Unit;

use Athwari\ZktecoAdms\Exceptions\CommandQueueFullException;
use Athwari\ZktecoAdms\Exceptions\DeviceNotFoundException;
use Athwari\ZktecoAdms\Services\CommandManager;
use Athwari\ZktecoAdms\Services\DeviceManager;
use Athwari\ZktecoAdms\Tests\TestCase;

class CommandManagerTest extends TestCase
{
    private CommandManager $commandManager;
    private DeviceManager $deviceManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandManager = app(CommandManager::class);
        $this->deviceManager = app(DeviceManager::class);
    }

    public function test_queue_command(): void
    {
        $this->deviceManager->registerDevice('TEST001');

        $id = $this->commandManager->queueCommand('TEST001', 'INFO');

        $this->assertGreaterThan(0, $id);
        $this->assertEquals(1, $this->commandManager->pendingCount('TEST001'));
    }

    public function test_queue_command_for_unknown_device(): void
    {
        $this->expectException(DeviceNotFoundException::class);
        $this->commandManager->queueCommand('UNKNOWN', 'INFO');
    }

    public function test_queue_command_limit(): void
    {
        config()->set('zkteco-adms.max_commands_per_device', 2);
        $this->deviceManager->registerDevice('TEST001');

        $this->commandManager->queueCommand('TEST001', 'INFO');
        $this->commandManager->queueCommand('TEST001', 'CHECK');

        $this->expectException(CommandQueueFullException::class);
        $this->commandManager->queueCommand('TEST001', 'LOG');
    }

    public function test_drain_commands(): void
    {
        $this->deviceManager->registerDevice('TEST001');

        $this->commandManager->queueCommand('TEST001', 'INFO');
        $this->commandManager->queueCommand('TEST001', 'CHECK');

        $entries = $this->commandManager->drainCommands('TEST001');

        $this->assertCount(2, $entries);
        $this->assertEquals('INFO', $entries[0]->command);
        $this->assertEquals('CHECK', $entries[1]->command);

        // Wire format
        $this->assertMatchesRegularExpression('/^C:\d+:INFO\n$/', $entries[0]->toWireFormat());
    }

    public function test_drain_commands_marks_as_sent(): void
    {
        $this->deviceManager->registerDevice('TEST001');
        $this->commandManager->queueCommand('TEST001', 'INFO');

        $entries = $this->commandManager->drainCommands('TEST001');

        // No more pending (unsent) commands, but still counts sent ones
        $drained = $this->commandManager->drainCommands('TEST001');
        $this->assertCount(0, $drained);
    }

    public function test_confirm_command(): void
    {
        $this->deviceManager->registerDevice('TEST001');
        $id = $this->commandManager->queueCommand('TEST001', 'INFO');

        $this->commandManager->confirmCommand($id, 0);

        $queuedCmd = $this->commandManager->getQueuedCommand($id);
        $this->assertEquals('INFO', $queuedCmd);
    }

    public function test_convenience_commands(): void
    {
        $this->deviceManager->registerDevice('TEST001');

        $id = $this->commandManager->sendInfoCommand('TEST001');
        $this->assertGreaterThan(0, $id);
        $this->assertEquals('INFO', $this->commandManager->getQueuedCommand($id));

        $id = $this->commandManager->sendCheckCommand('TEST001');
        $this->assertEquals('CHECK', $this->commandManager->getQueuedCommand($id));

        $id = $this->commandManager->sendLogCommand('TEST001');
        $this->assertEquals('LOG', $this->commandManager->getQueuedCommand($id));

        $id = $this->commandManager->sendQueryUsersCommand('TEST001');
        $this->assertEquals('DATA QUERY USERINFO', $this->commandManager->getQueuedCommand($id));
    }

    public function test_send_user_add_command(): void
    {
        $this->deviceManager->registerDevice('TEST001');

        $id = $this->commandManager->sendUserAddCommand('TEST001', '1001', 'John Doe', 0, '12345');
        $cmd = $this->commandManager->getQueuedCommand($id);

        $this->assertStringContainsString('DATA UPDATE USERINFO', $cmd);
        $this->assertStringContainsString('PIN=1001', $cmd);
        $this->assertStringContainsString('Name=John Doe', $cmd);
    }

    public function test_send_user_delete_command(): void
    {
        $this->deviceManager->registerDevice('TEST001');

        $id = $this->commandManager->sendUserDeleteCommand('TEST001', '1001');
        $cmd = $this->commandManager->getQueuedCommand($id);

        $this->assertEquals('DATA DELETE USERINFO PIN=1001', $cmd);
    }

    public function test_reject_command_with_newlines(): void
    {
        $this->deviceManager->registerDevice('TEST001');

        $this->expectException(\InvalidArgumentException::class);
        $this->commandManager->queueCommand('TEST001', "INJECT\nC:999:EVIL");
    }
}
