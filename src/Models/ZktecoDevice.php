<?php

namespace Athwari\ZktecoAdms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for a registered ZKTeco device.
 *
 * @property int $id
 * @property string $serial_number
 * @property \Carbon\Carbon|null $last_activity
 * @property array|null $options
 * @property string|null $timezone
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ZktecoDevice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'options' => 'array',
        'last_activity' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('zkteco-adms.table_prefix', 'zkteco_') . 'devices');
    }

    /**
     * Check if the device is currently online based on the configured threshold.
     */
    public function isOnline(): bool
    {
        if ($this->last_activity === null) {
            return false;
        }

        $threshold = config('zkteco-adms.online_threshold', 120);

        return $this->last_activity->diffInSeconds(now()) <= $threshold;
    }

    /**
     * Get the effective timezone for this device.
     */
    public function getEffectiveTimezone(): string
    {
        return $this->timezone ?? config('zkteco-adms.default_timezone', 'UTC');
    }

    /**
     * Attendance logs from this device.
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(ZktecoAttendanceLog::class, 'device_serial_number', 'serial_number');
    }

    /**
     * Command logs for this device.
     */
    public function commandLogs(): HasMany
    {
        return $this->hasMany(ZktecoCommandLog::class, 'device_serial_number', 'serial_number');
    }
}
