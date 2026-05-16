# Laravel ZKTeco ADMS Server Package

A comprehensive Laravel package implementing the ZKTeco ADMS (Attendance Data Management System) HTTP protocol for biometric attendance devices. This package translates the design patterns from the Go `s0x90/zkteco-adms` library and the PHP `saifulcoder/adms-server-ZKTeco` project into a clean, idiomatic Laravel package.

## User Review Required

> [!IMPORTANT]
> **Package Namespace**: The plan uses `Athwari\ZktecoAdms` as the vendor/package namespace. Please confirm this is correct.

> [!IMPORTANT]
> **Database Driver**: The migrations use standard Laravel migrations (MySQL/PostgreSQL/SQLite). The `devices` and `attendance_logs` tables are persisted to the database. The in-memory device tracking (for heartbeat/online status) is **also** provided via a `DeviceManager` singleton. Please confirm if you want **database-only** persistence, **memory-only** (like the Go lib), or **both** (recommended — database for durable storage, memory for real-time status).

> [!IMPORTANT]
> **Event System**: The plan uses Laravel Events + Listeners for callbacks (replacing Go's callback channels). This means you can listen for `AttendanceReceived`, `DeviceRegistered`, `CommandResultReceived`, etc. via standard Laravel event listeners/subscribers. Please confirm this approach.

## Open Questions

> [!NOTE]
> **Queue Workers**: Should command result processing and attendance callbacks be dispatched to Laravel queues (async via `ShouldQueue`) by default, or executed synchronously? The plan defaults to synchronous event dispatch with an opt-in `ShouldQueue` interface on listener classes.

> [!NOTE]
> **API Authentication**: Should the ADMS endpoints be protected by any middleware (e.g., IP whitelist, API key)? The Go library has no auth; the plan provides an optional configurable middleware slot.

---

## Proposed Changes

### Package Structure Overview

```
laravel-zkteco-adms-server/
├── composer.json
├── LICENSE
├── README.md
├── config/
│   └── zkteco-adms.php                    # Package configuration
├── database/
│   └── migrations/
│       ├── create_zkteco_devices_table.php
│       ├── create_zkteco_attendance_logs_table.php
│       └── create_zkteco_command_logs_table.php
├── routes/
│   └── adms.php                           # ADMS protocol routes
├── src/
│   ├── ZktecoAdmsServiceProvider.php      # Service provider
│   ├── Facades/
│   │   └── ZktecoAdms.php                 # Facade
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── AdmsController.php         # Single controller for all ADMS endpoints
│   │   └── Middleware/
│   │       └── ValidateDeviceRequest.php  # SN validation + body size limit
│   ├── Models/
│   │   ├── ZktecoDevice.php               # Eloquent model for devices
│   │   ├── ZktecoAttendanceLog.php        # Eloquent model for attendance
│   │   └── ZktecoCommandLog.php           # Eloquent model for command history
│   ├── Services/
│   │   ├── DeviceManager.php              # Thread-safe device registry (in-memory + DB)
│   │   ├── CommandManager.php             # Command queuing and execution
│   │   └── AttendanceParser.php           # ATTLOG parsing with multi-format timestamps
│   ├── Events/
│   │   ├── AttendanceReceived.php
│   │   ├── DeviceRegistered.php
│   │   ├── DeviceInfoReceived.php
│   │   ├── CommandResultReceived.php
│   │   └── UserQueryReceived.php
│   ├── DTOs/
│   │   ├── AttendanceRecord.php           # Attendance data transfer object
│   │   ├── CommandEntry.php               # Queued command DTO
│   │   ├── CommandResult.php              # Command result DTO
│   │   ├── DeviceSnapshot.php             # Device inspection DTO
│   │   └── UserRecord.php                 # User query result DTO
│   ├── Enums/
│   │   ├── AttendanceStatus.php           # Check In/Out/Break enum
│   │   └── VerifyMode.php                 # Password/Fingerprint/Card/Face enum
│   └── Exceptions/
│       ├── DeviceLimitReachedException.php
│       ├── CommandQueueFullException.php
│       ├── InvalidSerialNumberException.php
│       └── DeviceNotFoundException.php
└── tests/
    ├── Feature/
    │   ├── CdataEndpointTest.php
    │   ├── GetRequestEndpointTest.php
    │   ├── DeviceCmdEndpointTest.php
    │   ├── RegistryEndpointTest.php
    │   └── InspectEndpointTest.php
    └── Unit/
        ├── AttendanceParserTest.php
        ├── DeviceManagerTest.php
        └── CommandManagerTest.php
```

---

### Configuration

#### [NEW] [zkteco-adms.php](file:///var/www/laravel-packages/athwari/laravel-zkteco-adms-server/config/zkteco-adms.php)

Publishable configuration file with all tunable options mirroring the Go `WithX` option functions:

- `max_body_size` — Max request body in bytes (default: 10MB)
- `online_threshold` — Seconds before device considered offline (default: 120)
- `max_devices` — Max registered devices, 0 = unlimited (default: 1000)
- `max_commands_per_device` — Per-device command queue depth, 0 = unlimited (default: 100)
- `enable_inspect` — Enable `/iclock/inspect` debug endpoint (default: false)
- `device_eviction_enabled` — Enable automatic stale device cleanup (default: true)
- `device_eviction_interval` — Eviction check interval in seconds (default: 300)
- `device_eviction_timeout` — Inactivity timeout for eviction in seconds (default: 86400)
- `default_timezone` — Fallback timezone for timestamp parsing (default: 'UTC')
- `route_prefix` — Route prefix (default: 'iclock')
- `route_middleware` — Middleware for ADMS routes (default: [])
- `table_prefix` — Database table prefix (default: 'zkteco_')

---

### Service Provider & Facade

#### [NEW] [ZktecoAdmsServiceProvider.php](file:///var/www/laravel-packages/athwari/laravel-zkteco-adms-server/src/ZktecoAdmsServiceProvider.php)

- Registers `DeviceManager` and `CommandManager` as singletons
- Publishes config, migrations
- Loads routes from `routes/adms.php`
- Registers middleware alias `zkteco.validate`

#### [NEW] [ZktecoAdms.php](file:///var/www/laravel-packages/athwari/laravel-zkteco-adms-server/src/Facades/ZktecoAdms.php)

Facade proxying to `DeviceManager` for convenient API access:
- `ZktecoAdms::listDevices()`
- `ZktecoAdms::isOnline($sn)`
- `ZktecoAdms::queueCommand($sn, $cmd)`
- `ZktecoAdms::sendInfoCommand($sn)`
- etc.

---

### HTTP Layer

#### [NEW] [AdmsController.php](file:///var/www/laravel-packages/athwari/laravel-zkteco-adms-server/src/Http/Controllers/AdmsController.php)

Single controller handling all five ADMS protocol endpoints:

| Method | Endpoint | Handler | Description |
|--------|----------|---------|-------------|
| GET/POST | `/iclock/cdata` | `handleCdata()` | Attendance logs, device info, user query results |
| GET/POST | `/iclock/registry` | `handleRegistry()` | Device registration & capabilities |
| GET | `/iclock/getrequest` | `handleGetRequest()` | Device polling for pending commands |
| POST | `/iclock/devicecmd` | `handleDeviceCmd()` | Command execution confirmations |
| GET | `/iclock/inspect` | `handleInspect()` | JSON device snapshot (opt-in) |

Each handler follows the exact same protocol flow as the Go library:
1. Validate SN query parameter
2. Register/update device activity
3. Parse body based on `table` parameter (ATTLOG, OPERLOG, USERINFO, or INFO)
4. Dispatch Laravel events
5. Return protocol-appropriate response (`OK`, `C:<ID>:<CMD>\n`, etc.)

#### [NEW] [ValidateDeviceRequest.php](file:///var/www/laravel-packages/athwari/laravel-zkteco-adms-server/src/Http/Middleware/ValidateDeviceRequest.php)

Middleware that:
- Validates SN parameter format (1-64 alphanumeric/hyphen/underscore chars)
- Enforces max body size limit
- Rejects oversized payloads with 413
- Rejects invalid SNs with 400

---

### Database Layer

#### [NEW] Migrations

**`create_zkteco_devices_table`**: `serial_number` (unique), `last_activity`, `options` (JSON), `timezone`, timestamps

**`create_zkteco_attendance_logs_table`**: `device_serial_number`, `user_id`, `timestamp`, `status`, `verify_mode`, `work_code`, timestamps

**`create_zkteco_command_logs_table`**: `device_serial_number`, `command_id`, `command`, `status` (pending/sent/confirmed/failed), `return_code`, `queued_at`, `sent_at`, `confirmed_at`

#### [NEW] Eloquent Models

- `ZktecoDevice` — Casts `options` as array, has `isOnline()` accessor, relationships to attendance logs and commands
- `ZktecoAttendanceLog` — Belongs to device, casts `timestamp` as datetime
- `ZktecoCommandLog` — Belongs to device, tracks command lifecycle

---

### Core Services

#### [NEW] [DeviceManager.php](file:///var/www/laravel-packages/athwari/laravel-zkteco-adms-server/src/Services/DeviceManager.php)

Equivalent to Go's `ADMSServer` device-management methods. Uses a combination of database persistence and an in-memory cache for real-time status tracking:

- `registerDevice(string $sn)` — Register or update device, enforce max limit
- `getDevice(string $sn)` — Get device info
- `listDevices()` — List all registered devices
- `isOnline(string $sn)` — Check if device active within threshold
- `updateActivity(string $sn)` — Touch last_activity timestamp
- `setDeviceTimezone(string $sn, string $tz)` — Set device timezone
- `evictStaleDevices()` — Remove inactive devices (called by scheduled command)

#### [NEW] [CommandManager.php](file:///var/www/laravel-packages/athwari/laravel-zkteco-adms-server/src/Services/CommandManager.php)

Equivalent to Go's command queuing system:

- `queueCommand(string $sn, string $command): int` — Queue command, return ID
- `drainCommands(string $sn): array` — Get and clear pending commands
- `pendingCount(string $sn): int` — Count pending commands
- `sendInfoCommand(string $sn): int`
- `sendCheckCommand(string $sn): int`
- `sendUserAddCommand(string $sn, string $pin, string $name, int $privilege, string $card): int`
- `sendUserDeleteCommand(string $sn, string $pin): int`
- `sendGetOptionCommand(string $sn, string $key): int`
- `sendQueryUsersCommand(string $sn): int`
- `sendShellCommand(string $sn, string $command): int`
- `sendLogCommand(string $sn): int`

Wire format: `C:<ID>:<CMD>\n` exactly matching the Go implementation.

#### [NEW] [AttendanceParser.php](file:///var/www/laravel-packages/athwari/laravel-zkteco-adms-server/src/Services/AttendanceParser.php)

Equivalent to Go's `parser.go`:

- `parseAttendanceRecords(string $data, string $sn, string $tz): array` — Tab-separated ATTLOG parsing with multi-format timestamp support (`Y-m-d H:i:s` and Unix epoch)
- `parseKVPairs(string $data, string $sep, ?callable $keyTransform): array` — Generic key=value parser
- `parseUserRecords(string $data, string $sn): array` — USERINFO tab-separated parsing
- `parseCommandResults(string $body, string $sn): array` — devicecmd confirmation parsing (batched + multiline)
- `validateSerialNumber(string $sn): bool` — Regex validation

---

### Events & DTOs

#### Events (fire-and-forget, replaceable with queue listeners)

| Event | Payload | Triggered By |
|-------|---------|--------------|
| `AttendanceReceived` | `AttendanceRecord[]`, device SN | POST `/iclock/cdata?table=ATTLOG` |
| `DeviceRegistered` | device SN, options map | POST `/iclock/registry` |
| `DeviceInfoReceived` | device SN, info map | POST `/iclock/cdata` (INFO) |
| `CommandResultReceived` | `CommandResult` | POST `/iclock/devicecmd` |
| `UserQueryReceived` | `UserRecord[]`, device SN | POST `/iclock/cdata?table=USERINFO` |

#### DTOs (readonly value objects)

| DTO | Fields |
|-----|--------|
| `AttendanceRecord` | `userId`, `timestamp`, `status`, `verifyMode`, `workCode`, `serialNumber` |
| `CommandEntry` | `id`, `command` |
| `CommandResult` | `serialNumber`, `id`, `returnCode`, `command`, `queuedCommand` |
| `DeviceSnapshot` | `serial`, `lastActivity`, `online`, `options`, `timezone` |
| `UserRecord` | `pin`, `name`, `privilege`, `card`, `password` |

#### Enums (PHP 8.1+ backed enums)

| Enum | Values |
|------|--------|
| `AttendanceStatus` | CheckIn(0), CheckOut(1), BreakOut(2), BreakIn(3), OvertimeIn(4), OvertimeOut(5) |
| `VerifyMode` | Password(0), Fingerprint(1), Card(4), Face(15), Palm(25), etc. |

---

### Routes

#### [NEW] [adms.php](file:///var/www/laravel-packages/athwari/laravel-zkteco-adms-server/routes/adms.php)

```php
Route::prefix(config('zkteco-adms.route_prefix', 'iclock'))
    ->middleware(config('zkteco-adms.route_middleware', []))
    ->group(function () {
        Route::match(['get', 'post'], '/cdata', [AdmsController::class, 'handleCdata']);
        Route::match(['get', 'post'], '/registry', [AdmsController::class, 'handleRegistry']);
        Route::get('/getrequest', [AdmsController::class, 'handleGetRequest']);
        Route::post('/devicecmd', [AdmsController::class, 'handleDeviceCmd']);
        Route::get('/inspect', [AdmsController::class, 'handleInspect']);
    });
```

---

### Exceptions

Custom exceptions mirroring Go sentinel errors:

| Exception | Equivalent Go Error |
|-----------|-------------------|
| `DeviceLimitReachedException` | `ErrMaxDevicesReached` |
| `CommandQueueFullException` | `ErrCommandQueueFull` |
| `InvalidSerialNumberException` | `ErrInvalidSerialNumber` |
| `DeviceNotFoundException` | `ErrDeviceNotFound` |

---

### Stale Device Eviction (Scheduled Command)

A Laravel artisan command `zkteco:evict-stale-devices` will be registered and can be scheduled in the app's `Kernel.php`:

```php
$schedule->command('zkteco:evict-stale-devices')->everyFiveMinutes();
```

This replaces the Go library's background goroutine-based eviction worker.

---

## Verification Plan

### Automated Tests

- **Unit tests** for `AttendanceParser` (multi-format timestamps, malformed lines, edge cases)
- **Unit tests** for `DeviceManager` (registration, limits, eviction, online status)
- **Unit tests** for `CommandManager` (queuing, draining, limits, wire format)
- **Feature tests** for each ADMS endpoint using Laravel's HTTP testing:
  - `GET /iclock/cdata?SN=TEST001` → handshake response
  - `POST /iclock/cdata?SN=TEST001&table=ATTLOG` with body → attendance parsed
  - `GET /iclock/getrequest?SN=TEST001` → pending commands or `OK`
  - `POST /iclock/devicecmd?SN=TEST001` → command result processing
  - `GET/POST /iclock/registry?SN=TEST001` → device registration
  - `GET /iclock/inspect` → JSON snapshot (enabled/disabled)
  - Invalid SN → 400
  - Missing SN → 400
  - Oversized body → 413
  - Device limit reached → 503

```bash
php artisan test --filter=Zkteco
```

### Manual Verification

- Test with actual ZKTeco device configured to push to the Laravel server
- Verify attendance records are stored in the database
- Verify commands are queued and delivered to devices on poll
