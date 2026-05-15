<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request Body Limits
    |--------------------------------------------------------------------------
    |
    | Maximum allowed request body size in bytes. Requests exceeding this
    | limit will receive a 413 (Request Entity Too Large) response.
    | Default: 10 MB (10485760 bytes).
    |
    */

    'max_body_size' => env('ZKTECO_MAX_BODY_SIZE', 10 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Online Threshold
    |--------------------------------------------------------------------------
    |
    | Duration in seconds after which a device is considered offline if no
    | activity has been received. Default: 120 seconds (2 minutes).
    |
    */

    'online_threshold' => env('ZKTECO_ONLINE_THRESHOLD', 120),

    /*
    |--------------------------------------------------------------------------
    | Device Limits
    |--------------------------------------------------------------------------
    |
    | Maximum number of devices that can be registered simultaneously.
    | When the limit is reached, new device registrations are rejected
    | with a 503 Service Unavailable response. Set to 0 for unlimited.
    | Default: 1000.
    |
    */

    'max_devices' => env('ZKTECO_MAX_DEVICES', 1000),

    /*
    |--------------------------------------------------------------------------
    | Command Queue Limits
    |--------------------------------------------------------------------------
    |
    | Maximum number of queued commands per device. When the limit is
    | reached, QueueCommand returns a CommandQueueFullException.
    | Set to 0 for unlimited. Default: 100.
    |
    */

    'max_commands_per_device' => env('ZKTECO_MAX_COMMANDS_PER_DEVICE', 100),

    /*
    |--------------------------------------------------------------------------
    | Inspect Endpoint
    |--------------------------------------------------------------------------
    |
    | Enable the /iclock/inspect debug endpoint that returns a JSON snapshot
    | of all registered devices. Disabled by default because it exposes
    | device metadata without authentication.
    |
    */

    'enable_inspect' => env('ZKTECO_ENABLE_INSPECT', false),

    /*
    |--------------------------------------------------------------------------
    | Device Eviction
    |--------------------------------------------------------------------------
    |
    | Automatic cleanup of stale devices that have been inactive longer
    | than the eviction timeout. The scheduled command
    | `zkteco:evict-stale-devices` handles this.
    |
    */

    'device_eviction_enabled' => env('ZKTECO_DEVICE_EVICTION_ENABLED', true),

    // Eviction check interval in seconds (default: 300 = 5 minutes).
    'device_eviction_interval' => env('ZKTECO_DEVICE_EVICTION_INTERVAL', 300),

    // Inactivity timeout for eviction in seconds (default: 86400 = 24 hours).
    'device_eviction_timeout' => env('ZKTECO_DEVICE_EVICTION_TIMEOUT', 86400),

    /*
    |--------------------------------------------------------------------------
    | Default Timezone
    |--------------------------------------------------------------------------
    |
    | Fallback timezone used to interpret attendance timestamps from devices
    | that have no explicit timezone configured. Default: 'UTC'.
    |
    */

    'default_timezone' => env('ZKTECO_DEFAULT_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Prefix and middleware for the ADMS protocol routes.
    |
    */

    'route_prefix' => env('ZKTECO_ROUTE_PREFIX', 'iclock'),

    'route_middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Database Table Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all database tables created by this package.
    | Default: 'zkteco_'.
    |
    */

    'table_prefix' => 'zkteco_',

];
