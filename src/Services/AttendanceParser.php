<?php

namespace Athwari\ZktecoAdms\Services;

use Athwari\ZktecoAdms\DTOs\AttendanceRecord;
use Athwari\ZktecoAdms\DTOs\CommandResult;
use Athwari\ZktecoAdms\DTOs\UserRecord;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\Log;

/**
 * Parser for ZKTeco ADMS protocol data formats.
 *
 * Handles parsing of:
 * - ATTLOG attendance records (tab-separated, multi-format timestamps)
 * - Key=value pairs (device info and registry payloads)
 * - USERINFO records (tab-separated key=value fields)
 * - Command result confirmations (batched and multiline formats)
 * - Serial number validation
 */
class AttendanceParser
{
    /** Maximum allowed serial number length. */
    private const MAX_SERIAL_NUMBER_LENGTH = 64;

    /** Regex pattern for valid serial numbers: 1-64 alphanumeric, hyphens, or underscores. */
    private const SERIAL_NUMBER_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    /** ZKTeco timestamp format. */
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    /** Maximum body preview length for logging. */
    private const MAX_BODY_PREVIEW_LEN = 200;

    /** ATTLOG tab-separated field indices. */
    private const ATT_FIELD_USER_ID = 0;
    private const ATT_FIELD_TIMESTAMP = 1;
    private const ATT_FIELD_STATUS = 2;
    private const ATT_FIELD_VERIFY_MODE = 3;
    private const ATT_FIELD_WORK_CODE = 4;
    private const ATT_MIN_FIELDS = 2;

    /**
     * Validate that a serial number matches the expected format.
     *
     * Valid serial numbers are 1-64 characters consisting of alphanumeric
     * characters, hyphens, or underscores.
     */
    public function validateSerialNumber(string $sn): bool
    {
        if ($sn === '' || strlen($sn) > self::MAX_SERIAL_NUMBER_LENGTH) {
            return false;
        }

        return (bool) preg_match(self::SERIAL_NUMBER_PATTERN, $sn);
    }

    /**
     * Parse attendance records from device ATTLOG data.
     *
     * Each line must have at least a UserID and a parseable timestamp
     * (either "Y-m-d H:i:s" format or a Unix epoch integer). Malformed
     * lines are skipped and logged so downstream systems never receive
     * zero-value timestamps.
     *
     * @param string $data Raw ATTLOG body from the device
     * @param string $serialNumber Device serial number
     * @param string $timezone Timezone name for interpreting device-local timestamps
     * @return AttendanceRecord[]
     */
    public function parseAttendanceRecords(string $data, string $serialNumber, string $timezone = 'UTC'): array
    {
        $records = [];
        $skipped = 0;

        try {
            $tz = new DateTimeZone($timezone);
        } catch (\Exception) {
            $tz = new DateTimeZone('UTC');
            Log::warning('Invalid timezone, falling back to UTC', [
                'timezone' => $timezone,
                'device' => $serialNumber,
            ]);
        }

        $lines = explode("\n", trim($data, "\n\r"));

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if (trim($line) === '') {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) < self::ATT_MIN_FIELDS) {
                $skipped++;
                Log::warning('Skipping malformed ATTLOG line', [
                    'device' => $serialNumber,
                    'fields' => count($parts),
                    'line' => $line,
                ]);
                continue;
            }

            $userId = trim($parts[self::ATT_FIELD_USER_ID]);
            if ($userId === '') {
                $skipped++;
                Log::warning('Skipping ATTLOG line with empty UserID', [
                    'device' => $serialNumber,
                    'line' => $line,
                ]);
                continue;
            }

            // Try parsing as "Y-m-d H:i:s" format first, then as Unix epoch
            $timestamp = $this->parseTimestamp($parts[self::ATT_FIELD_TIMESTAMP], $tz);
            if ($timestamp === null) {
                $skipped++;
                Log::warning('Skipping ATTLOG line with unparseable timestamp', [
                    'device' => $serialNumber,
                    'timestamp' => $parts[self::ATT_FIELD_TIMESTAMP],
                    'line' => $line,
                ]);
                continue;
            }

            $status = 0;
            if (isset($parts[self::ATT_FIELD_STATUS])) {
                $status = filter_var($parts[self::ATT_FIELD_STATUS], FILTER_VALIDATE_INT);
                if ($status === false) {
                    Log::warning('Non-integer Status field, defaulting to 0', [
                        'device' => $serialNumber,
                        'value' => $parts[self::ATT_FIELD_STATUS],
                    ]);
                    $status = 0;
                }
            }

            $verifyMode = 0;
            if (isset($parts[self::ATT_FIELD_VERIFY_MODE])) {
                $verifyMode = filter_var($parts[self::ATT_FIELD_VERIFY_MODE], FILTER_VALIDATE_INT);
                if ($verifyMode === false) {
                    Log::warning('Non-integer VerifyMode field, defaulting to 0', [
                        'device' => $serialNumber,
                        'value' => $parts[self::ATT_FIELD_VERIFY_MODE],
                    ]);
                    $verifyMode = 0;
                }
            }

            $workCode = $parts[self::ATT_FIELD_WORK_CODE] ?? '';

            $records[] = new AttendanceRecord(
                userId: $userId,
                timestamp: $timestamp,
                status: $status,
                verifyMode: $verifyMode,
                workCode: $workCode,
                serialNumber: $serialNumber,
            );
        }

        if ($skipped > 0) {
            Log::warning('Skipped malformed ATTLOG lines', [
                'device' => $serialNumber,
                'skipped' => $skipped,
                'total' => count($records) + $skipped,
            ]);
        }

        return $records;
    }

    /**
     * Parse key=value pairs separated by a given separator.
     *
     * Generalises the two device parsers:
     * - Device info: sep="\n", keyTransform=null
     * - Registry: sep=",", keyTransform=trimTildePrefix
     *
     * @param string $data Raw data string
     * @param string $separator Separator between key=value pairs
     * @param callable|null $keyTransform Optional transformation applied to each key
     * @return array<string, string>
     */
    public function parseKVPairs(string $data, string $separator = "\n", ?callable $keyTransform = null): array
    {
        $info = [];
        $parts = explode($separator, trim($data));

        foreach ($parts as $part) {
            $part = trim($part);
            $eqPos = strpos($part, '=');
            if ($eqPos !== false) {
                $key = trim(substr($part, 0, $eqPos));
                $value = trim(substr($part, $eqPos + 1));

                if ($keyTransform !== null) {
                    $key = $keyTransform($key);
                }

                $info[$key] = $value;
            }
        }

        return $info;
    }

    /**
     * Parse user records from USERINFO data pushed by the device.
     *
     * Each line has tab-separated key=value fields like:
     *   PIN=1\tName=John\tPrivilege=0\tCard=\tPassword=
     *
     * Lines that lack a PIN field are skipped with a warning.
     *
     * @param string $data Raw USERINFO body from the device
     * @param string $serialNumber Device serial number
     * @return UserRecord[]
     */
    public function parseUserRecords(string $data, string $serialNumber): array
    {
        $records = [];
        $skipped = 0;

        $lines = explode("\n", trim($data));

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if ($line === '') {
                continue;
            }

            $fields = [];
            $parts = explode("\t", $line);
            foreach ($parts as $part) {
                $eqPos = strpos($part, '=');
                if ($eqPos !== false) {
                    $key = trim(substr($part, 0, $eqPos));
                    $value = trim(substr($part, $eqPos + 1));
                    $fields[$key] = $value;
                }
            }

            $pin = $fields['PIN'] ?? '';
            if ($pin === '') {
                $skipped++;
                Log::warning('Skipping USERINFO line without PIN', [
                    'device' => $serialNumber,
                    'line_len' => strlen($line),
                ]);
                continue;
            }

            $privilege = filter_var($fields['Privilege'] ?? '0', FILTER_VALIDATE_INT);
            if ($privilege === false) {
                $privilege = 0;
            }

            $records[] = new UserRecord(
                pin: $pin,
                name: $fields['Name'] ?? '',
                privilege: $privilege,
                card: $fields['Card'] ?? '',
                password: $fields['Password'] ?? '',
            );
        }

        if ($skipped > 0) {
            Log::warning('Skipped malformed USERINFO lines', [
                'device' => $serialNumber,
                'skipped' => $skipped,
                'total' => count($records) + $skipped,
            ]);
        }

        return $records;
    }

    /**
     * Parse command result confirmations from a devicecmd body.
     *
     * The device uses two different formats:
     *
     * Batched format (ampersand-separated KV pairs, one result per line):
     *   ID=1&Return=0&CMD=INFO\nID=2&Return=0&CMD=CHECK\n
     *
     * Shell/multiline format (newline-separated KV pairs, single result):
     *   ID=32\nReturn=0\nCMD=Shell\nContent=output\n
     *
     * The parser accumulates key=value pairs into the current result. When a
     * new ID= is encountered, it flushes the previous result and starts a new one.
     *
     * @param string $body Raw devicecmd body
     * @param string $serialNumber Device serial number
     * @return CommandResult[]
     */
    public function parseCommandResults(string $body, string $serialNumber): array
    {
        $results = [];
        $currentId = null;
        $currentReturnCode = 0;
        $currentCommand = '';
        $hasId = false;

        // Normalize: treat both \n and & as delimiters between KV pairs.
        $body = str_replace("\n", '&', $body);
        $parts = explode('&', $body);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $eqPos = strpos($part, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = strtoupper(trim(substr($part, 0, $eqPos)));
            $value = trim(substr($part, $eqPos + 1));

            switch ($key) {
                case 'ID':
                    $id = filter_var($value, FILTER_VALIDATE_INT);
                    if ($id === false) {
                        Log::warning('devicecmd: unparseable ID', [
                            'device' => $serialNumber,
                            'value' => $value,
                        ]);
                        continue 2;
                    }

                    // New ID means new result — flush the previous one.
                    if ($hasId) {
                        $results[] = new CommandResult(
                            serialNumber: $serialNumber,
                            id: $currentId,
                            returnCode: $currentReturnCode,
                            command: $currentCommand,
                        );
                    }

                    $currentId = $id;
                    $currentReturnCode = 0;
                    $currentCommand = '';
                    $hasId = true;
                    break;

                case 'RETURN':
                    $code = filter_var($value, FILTER_VALIDATE_INT);
                    if ($code !== false) {
                        $currentReturnCode = $code;
                    } else {
                        Log::warning('devicecmd: unparseable Return', [
                            'device' => $serialNumber,
                            'value' => $value,
                        ]);
                    }
                    break;

                case 'CMD':
                    $currentCommand = $value;
                    break;
            }
        }

        // Flush the last result
        if ($hasId) {
            $results[] = new CommandResult(
                serialNumber: $serialNumber,
                id: $currentId,
                returnCode: $currentReturnCode,
                command: $currentCommand,
            );
        }

        return $results;
    }

    /**
     * Remove a leading "~" prefix from a string.
     *
     * Used as a key transform for registry body parsing, where some ZKTeco
     * devices prefix keys with "~".
     */
    public static function trimTildePrefix(string $s): string
    {
        return ltrim($s, '~');
    }

    /**
     * Return a truncated preview of body data for logging.
     */
    public static function bodyPreview(string $body): string
    {
        if (strlen($body) > self::MAX_BODY_PREVIEW_LEN) {
            return substr($body, 0, self::MAX_BODY_PREVIEW_LEN) . '...';
        }

        return $body;
    }

    /**
     * Parse a timestamp string, trying "Y-m-d H:i:s" format first,
     * then Unix epoch integer.
     */
    private function parseTimestamp(string $value, DateTimeZone $tz): ?Carbon
    {
        $value = trim($value);

        // Try "Y-m-d H:i:s" format
        try {
            return Carbon::createFromFormat(self::TIMESTAMP_FORMAT, $value, $tz);
        } catch (\Exception) {
            // Fall through to epoch check
        }

        // Try Unix epoch integer
        if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
            $epoch = (int) $value;

            return Carbon::createFromTimestamp($epoch);
        }

        return null;
    }
}
