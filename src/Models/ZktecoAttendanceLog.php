<?php

namespace Athwari\ZktecoAdms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for attendance log records.
 *
 * @property int $id
 * @property string $device_serial_number
 * @property string $user_id
 * @property \Carbon\Carbon $recorded_at
 * @property int $status
 * @property int $verify_mode
 * @property string $work_code
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ZktecoAttendanceLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'recorded_at' => 'datetime',
        'status' => 'integer',
        'verify_mode' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('zkteco-adms.table_prefix', 'zkteco_') . 'attendance_logs');
    }

    /**
     * Get the device that generated this log.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(ZktecoDevice::class, 'device_serial_number', 'serial_number');
    }
}
