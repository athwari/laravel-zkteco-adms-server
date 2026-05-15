<?php

namespace Athwari\ZktecoAdms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for command log records tracking command lifecycle.
 *
 * @property int $id
 * @property string $device_serial_number
 * @property int $command_id
 * @property string $command
 * @property string $status
 * @property int|null $return_code
 * @property \Carbon\Carbon|null $queued_at
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon|null $confirmed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ZktecoCommandLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'command_id' => 'integer',
        'return_code' => 'integer',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('zkteco-adms.table_prefix', 'zkteco_') . 'command_logs');
    }

    /**
     * Get the device this command was sent to.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(ZktecoDevice::class, 'device_serial_number', 'serial_number');
    }

    /**
     * Whether this command completed successfully.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed' && $this->return_code === 0;
    }
}
