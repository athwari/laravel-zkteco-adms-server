<?php

namespace Athwari\ZktecoAdms\Enums;

/**
 * Attendance status values reported by ZKTeco devices.
 *
 * These values represent the punch state when a user interacts
 * with the biometric device via the ADMS (Push) HTTP protocol.
 */
enum AttendanceStatus: int
{
    case CheckIn = 0;
    case CheckOut = 1;
    case BreakOut = 2;
    case BreakIn = 3;
    case OvertimeIn = 4;
    case OvertimeOut = 5;

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::CheckIn => 'Check In',
            self::CheckOut => 'Check Out',
            self::BreakOut => 'Break Out',
            self::BreakIn => 'Break In',
            self::OvertimeIn => 'Overtime In',
            self::OvertimeOut => 'Overtime Out',
        };
    }

    /**
     * Try to create from an integer value, returning null for unknown values.
     */
    public static function tryFromValue(int $value): ?self
    {
        return self::tryFrom($value);
    }
}
