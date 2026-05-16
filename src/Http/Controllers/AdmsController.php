<?php

namespace Athwari\ZktecoAdms\Http\Controllers;

use Athwari\ZktecoAdms\DTOs\CommandResult;
use Athwari\ZktecoAdms\Events\AttendanceReceived;
use Athwari\ZktecoAdms\Events\CommandResultReceived;
use Athwari\ZktecoAdms\Events\DeviceInfoReceived;
use Athwari\ZktecoAdms\Events\DeviceRegistered;
use Athwari\ZktecoAdms\Events\UserQueryReceived;
use Athwari\ZktecoAdms\Exceptions\DeviceLimitReachedException;
use Athwari\ZktecoAdms\Exceptions\InvalidSerialNumberException;
use Athwari\ZktecoAdms\Models\ZktecoAttendanceLog;
use Athwari\ZktecoAdms\Services\AttendanceParser;
use Athwari\ZktecoAdms\Services\CommandManager;
use Athwari\ZktecoAdms\Services\DeviceManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Controller handling all five ADMS protocol endpoints.
 *
 * Endpoints:
 *   GET/POST /iclock/cdata       - Attendance logs, device info, user query results
 *   GET/POST /iclock/registry    - Device registration & capabilities
 *   GET      /iclock/getrequest  - Device polling for pending commands
 *   POST     /iclock/devicecmd   - Command execution confirmations
 *   GET      /iclock/inspect     - JSON device snapshot (opt-in)
 */
class AdmsController extends Controller
{
    public function __construct(
        private readonly DeviceManager $deviceManager,
        private readonly CommandManager $commandManager,
        private readonly AttendanceParser $parser,
    ) {}

    /**
     * Handle /iclock/cdata endpoint for attendance data, device info, and user queries.
     */
    public function handleCdata(Request $request): Response
    {
        $serialNumber = $this->requireDevice($request);
        if ($serialNumber instanceof Response) {
            return $serialNumber;
        }

        $table = $request->query('table', '');

        Log::debug('cdata request', [
            'method' => $request->method(),
            'device' => $serialNumber,
            'table' => $table,
        ]);

        switch ($table) {
            case 'ATTLOG':
                return $this->handleAttLog($request, $serialNumber);

            case 'OPERLOG':
                return response('OK', 200);

            case 'USERINFO':
                return $this->handleUserInfo($request, $serialNumber);

            default:
                return $this->handleInfoOrCommands($request, $serialNumber);
        }
    }

    /**
     * Handle /iclock/getrequest endpoint — device polling for commands.
     */
    public function handleGetRequest(Request $request): Response
    {
        $serialNumber = $this->requireDevice($request);
        if ($serialNumber instanceof Response) {
            return $serialNumber;
        }

        Log::debug('getrequest', ['device' => $serialNumber]);

        return $this->writeCommandsOrOK($serialNumber);
    }

    /**
     * Handle /iclock/devicecmd endpoint — command execution confirmations.
     */
    public function handleDeviceCmd(Request $request): Response
    {
        $serialNumber = $this->requireDevice($request);
        if ($serialNumber instanceof Response) {
            return $serialNumber;
        }

        Log::debug('devicecmd', ['device' => $serialNumber]);

        $body = $request->getContent();

        if (strlen($body) > 0) {
            Log::debug('devicecmd body', [
                'device' => $serialNumber,
                'preview' => AttendanceParser::bodyPreview($body),
            ]);
        }

        $results = $this->parser->parseCommandResults($body, $serialNumber);

        foreach ($results as $result) {
            // Enrich with the original queued command string
            $queuedCommand = $this->commandManager->getQueuedCommand($result->id);
            $enrichedResult = new CommandResult(
                serialNumber: $result->serialNumber,
                id: $result->id,
                returnCode: $result->returnCode,
                command: $result->command,
                queuedCommand: $queuedCommand,
            );

            Log::info('Command result', [
                'device' => $serialNumber,
                'id' => $enrichedResult->id,
                'return' => $enrichedResult->returnCode,
                'cmd' => $enrichedResult->command,
            ]);

            // Mark the command as confirmed in the database
            $this->commandManager->confirmCommand($enrichedResult->id, $enrichedResult->returnCode);

            // Dispatch event
            event(new CommandResultReceived($enrichedResult));
        }

        return response('OK', 200);
    }

    /**
     * Handle /iclock/registry endpoint — device registration & capabilities.
     */
    public function handleRegistry(Request $request): Response
    {
        $serialNumber = $this->requireDevice($request);
        if ($serialNumber instanceof Response) {
            return $serialNumber;
        }

        Log::debug('registry request', [
            'method' => $request->method(),
            'device' => $serialNumber,
        ]);

        $body = $request->getContent();

        if (strlen($body) > 0) {
            Log::debug('registry body', ['preview' => AttendanceParser::bodyPreview($body)]);

            // Registry bodies use comma-separated key=value pairs with optional ~ prefix on keys
            $info = $this->parser->parseKVPairs($body, ',', [AttendanceParser::class, 'trimTildePrefix']);

            // Update device options
            $this->deviceManager->updateDeviceOptions($serialNumber, $info);

            // Dispatch event
            event(new DeviceRegistered($serialNumber, $info));
        }

        return response('OK', 200);
    }

    /**
     * Handle /iclock/inspect endpoint — JSON device snapshot.
     */
    public function handleInspect(Request $request): Response
    {
        if (! config('zkteco-adms.enable_inspect', false)) {
            return response('Not Found', 404);
        }

        $snapshots = $this->deviceManager->getDeviceSnapshots();

        $payload = [
            'devices' => array_map(fn ($s) => $s->toArray(), $snapshots),
            'count' => count($snapshots),
            'time' => now()->toIso8601String(),
        ];

        return response(json_encode($payload), 200)
            ->header('Content-Type', 'application/json');
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Validate SN, register device, and update activity.
     * Returns serial number string on success, or a Response on failure.
     */
    private function requireDevice(Request $request): string|Response
    {
        $sn = (string) $request->query('SN', '');

        if ($sn === '') {
            return response('Missing SN parameter', 400);
        }

        if (! $this->parser->validateSerialNumber($sn)) {
            Log::warning('Invalid serial number', ['sn' => $sn]);

            return response('Invalid SN parameter', 400);
        }

        try {
            $this->deviceManager->registerDevice($sn);
        } catch (DeviceLimitReachedException $e) {
            Log::warning('Device limit reached', [
                'device' => $sn,
                'limit' => config('zkteco-adms.max_devices'),
            ]);

            return response('Device limit reached', 503);
        } catch (InvalidSerialNumberException) {
            return response('Invalid SN parameter', 400);
        }

        $this->deviceManager->updateActivity($sn);

        return $sn;
    }

    /**
     * Handle ATTLOG table — parse and store attendance records.
     */
    private function handleAttLog(Request $request, string $serialNumber): Response
    {
        $body = $request->getContent();

        if (strlen($body) > 0) {
            Log::debug('ATTLOG body', ['preview' => AttendanceParser::bodyPreview($body)]);
        }

        $timezone = $this->deviceManager->getDeviceTimezone($serialNumber);
        $records = $this->parser->parseAttendanceRecords($body, $serialNumber, $timezone);

        // Persist to database
        foreach ($records as $record) {
            ZktecoAttendanceLog::create([
                'device_serial_number' => $record->serialNumber,
                'user_id' => $record->userId,
                'recorded_at' => $record->timestamp,
                'status' => $record->status,
                'verify_mode' => $record->verifyMode,
                'work_code' => $record->workCode,
            ]);
        }

        // Dispatch event
        if (count($records) > 0) {
            event(new AttendanceReceived($serialNumber, $records));
        }

        return response('OK: '.count($records), 200);
    }

    /**
     * Handle USERINFO table — parse user records from device query response.
     */
    private function handleUserInfo(Request $request, string $serialNumber): Response
    {
        $body = $request->getContent();

        if (strlen($body) > 0) {
            $users = $this->parser->parseUserRecords($body, $serialNumber);

            Log::debug('USERINFO records processed', [
                'count' => count($users),
                'device' => $serialNumber,
            ]);

            if (count($users) > 0) {
                event(new UserQueryReceived($serialNumber, $users));
            }
        }

        return response('OK', 200);
    }

    /**
     * Handle default case: device info POST or pending commands GET.
     */
    private function handleInfoOrCommands(Request $request, string $serialNumber): Response
    {
        if ($request->isMethod('POST')) {
            $body = $request->getContent();

            if (strlen($body) > 0) {
                $info = $this->parser->parseKVPairs($body, "\n");
                event(new DeviceInfoReceived($serialNumber, $info));
                Log::debug('INFO body', ['preview' => AttendanceParser::bodyPreview($body)]);
            }
        }

        return $this->writeCommandsOrOK($serialNumber);
    }

    /**
     * Drain pending commands and write them in wire format, or "OK" if none.
     */
    private function writeCommandsOrOK(string $serialNumber): Response
    {
        $commands = $this->commandManager->drainCommands($serialNumber);

        if (count($commands) > 0) {
            $output = '';
            foreach ($commands as $entry) {
                $output .= $entry->toWireFormat();
            }

            return response($output, 200);
        }

        return response('OK', 200);
    }
}
