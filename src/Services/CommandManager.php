<?php

namespace Athwari\ZktecoAdms\Services;

use Athwari\ZktecoAdms\DTOs\CommandEntry;
use Athwari\ZktecoAdms\Exceptions\CommandQueueFullException;
use Athwari\ZktecoAdms\Exceptions\DeviceNotFoundException;
use Athwari\ZktecoAdms\Exceptions\InvalidSerialNumberException;
use Athwari\ZktecoAdms\Models\ZktecoCommandLog;
use Athwari\ZktecoAdms\Models\ZktecoDevice;
use Illuminate\Support\Facades\Log;

/**
 * Manages command queuing and execution for ZKTeco devices.
 *
 * Commands are queued in the database and delivered to devices when
 * they poll via GET /iclock/getrequest or GET /iclock/cdata.
 * Wire format: "C:<ID>:<CMD>\n"
 */
class CommandManager
{
    /**
     * Queue a command to be sent to a device on its next poll.
     *
     * @throws DeviceNotFoundException If the device is not registered
     * @throws CommandQueueFullException If the per-device queue limit is reached
     * @throws \InvalidArgumentException If the command contains control characters
     * @return int The assigned command ID
     */
    public function queueCommand(string $serialNumber, string $command): int
    {
        $this->validateCommandField('command', $command);

        if (! ZktecoDevice::where('serial_number', $serialNumber)->exists()) {
            throw new DeviceNotFoundException($serialNumber);
        }

        $maxCommands = config('zkteco-adms.max_commands_per_device', 100);
        if ($maxCommands > 0) {
            $pendingCount = $this->pendingCount($serialNumber);
            if ($pendingCount >= $maxCommands) {
                throw new CommandQueueFullException($serialNumber, $maxCommands);
            }
        }

        $log = ZktecoCommandLog::create([
            'device_serial_number' => $serialNumber,
            'command_id' => 0, // Will be set to the record ID
            'command' => $command,
            'status' => 'pending',
            'queued_at' => now(),
        ]);

        // Use the auto-increment ID as the command ID
        $log->update(['command_id' => $log->id]);

        Log::debug('Command queued', [
            'device' => $serialNumber,
            'command_id' => $log->id,
            'command' => $command,
        ]);

        return (int) $log->id;
    }

    /**
     * Drain all pending commands for a device and mark them as sent.
     *
     * @return CommandEntry[]
     */
    public function drainCommands(string $serialNumber): array
    {
        $logs = ZktecoCommandLog::where('device_serial_number', $serialNumber)
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        $entries = [];
        foreach ($logs as $log) {
            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $entries[] = new CommandEntry(
                id: (int) $log->command_id,
                command: $log->command,
            );
        }

        return $entries;
    }

    /**
     * Get the number of pending (unsent) commands for a device.
     */
    public function pendingCount(string $serialNumber): int
    {
        return ZktecoCommandLog::where('device_serial_number', $serialNumber)
            ->whereIn('status', ['pending', 'sent'])
            ->count();
    }

    /**
     * Mark a command as confirmed by the device.
     */
    public function confirmCommand(int $commandId, int $returnCode): void
    {
        $log = ZktecoCommandLog::where('command_id', $commandId)->first();

        if ($log === null) {
            Log::warning('Command confirmation for unknown ID', ['command_id' => $commandId]);
            return;
        }

        $log->update([
            'status' => $returnCode === 0 ? 'confirmed' : 'failed',
            'return_code' => $returnCode,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Get the original command string for a given command ID.
     */
    public function getQueuedCommand(int $commandId): string
    {
        $log = ZktecoCommandLog::where('command_id', $commandId)->first();
        return $log?->command ?? '';
    }

    // ---------------------------------------------------------------
    // Convenience command methods
    // ---------------------------------------------------------------

    /** Queue an INFO command to request device information. */
    public function sendInfoCommand(string $serialNumber): int
    {
        return $this->queueCommand($serialNumber, 'INFO');
    }

    /** Queue a CHECK (heartbeat) command. */
    public function sendCheckCommand(string $serialNumber): int
    {
        return $this->queueCommand($serialNumber, 'CHECK');
    }

    /**
     * Queue a DATA UPDATE USERINFO command to add or update a user.
     *
     * Wire format: DATA UPDATE USERINFO PIN=<pin>\tName=<name>\tPrivilege=<priv>\tCard=<card>
     *
     * Note: the ADMS datasheet documents this as "USER ADD", but real devices
     * require DATA UPDATE USERINFO instead.
     */
    public function sendUserAddCommand(
        string $serialNumber,
        string $pin,
        string $name,
        int $privilege = 0,
        string $card = '',
    ): int {
        foreach (['pin' => $pin, 'name' => $name, 'card' => $card] as $field => $value) {
            $this->validateCommandField($field, $value);
        }

        $cmd = sprintf("DATA UPDATE USERINFO PIN=%s\tName=%s\tPrivilege=%d\tCard=%s", $pin, $name, $privilege, $card);
        return $this->queueCommand($serialNumber, $cmd);
    }

    /**
     * Queue a DATA DELETE USERINFO command to remove a user.
     *
     * Note: the full word DELETE is required — DATA DEL USERINFO fails.
     */
    public function sendUserDeleteCommand(string $serialNumber, string $pin): int
    {
        $this->validateCommandField('pin', $pin);
        return $this->queueCommand($serialNumber, sprintf('DATA DELETE USERINFO PIN=%s', $pin));
    }

    /**
     * Queue a GET OPTION command to retrieve a device configuration value.
     *
     * Confirmed keys: DeviceName, FWVersion, IPAddress, MACAddress, Platform,
     * WorkCode, LockCount, UserCount, FPCount, AttLogCount, FaceCount,
     * TransactionCount, MaxUserCount, MaxAttLogCount, MaxFingerCount, MaxFaceCount.
     */
    public function sendGetOptionCommand(string $serialNumber, string $key): int
    {
        $this->validateCommandField('key', $key);
        return $this->queueCommand($serialNumber, sprintf('GET OPTION FROM %s', $key));
    }

    /** Queue a DATA QUERY USERINFO command to request all user records. */
    public function sendQueryUsersCommand(string $serialNumber): int
    {
        return $this->queueCommand($serialNumber, 'DATA QUERY USERINFO');
    }

    /**
     * Queue a Shell command for execution on the device.
     *
     * WARNING: This executes arbitrary commands on the device's Linux OS.
     * Use with extreme caution.
     */
    public function sendShellCommand(string $serialNumber, string $command): int
    {
        $this->validateCommandField('command', $command);
        return $this->queueCommand($serialNumber, sprintf('Shell %s', $command));
    }

    /** Queue a LOG command to request log data from the device. */
    public function sendLogCommand(string $serialNumber): int
    {
        return $this->queueCommand($serialNumber, 'LOG');
    }

    /**
     * Validate that a command field does not contain control characters
     * that could cause injection on the ADMS wire protocol.
     *
     * @throws \InvalidArgumentException
     */
    private function validateCommandField(string $name, string $value): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException(
                "Command field '{$name}' contains forbidden control characters."
            );
        }
    }
}
