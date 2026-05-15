<?php

namespace Athwari\ZktecoAdms\DTOs;

use Athwari\ZktecoAdms\Enums\AttendanceStatus;
use Athwari\ZktecoAdms\Enums\VerifyMode;
use DateTimeInterface;

/**
 * Represents an attendance transaction from a ZKTeco device.
 *
 * This is a read-only data transfer object created by the AttendanceParser
 * when processing ATTLOG data from the device.
 */
final readonly class AttendanceRecord
{
    public function __construct(
        public string $userId,
        public DateTimeInterface $timestamp,
        public int $status,
        public int $verifyMode,
        public string $workCode,
        public string $serialNumber,
    ) {}

    /**
     * Get the attendance status as an enum, or null for unknown values.
     */
    public function statusEnum(): ?AttendanceStatus
    {
        return AttendanceStatus::tryFrom($this->status);
    }

    /**
     * Get a human-readable status label.
     */
    public function statusLabel(): string
    {
        return $this->statusEnum()?->label() ?? "Unknown ({$this->status})";
    }

    /**
     * Get the verify mode as an enum, or null for unknown values.
     */
    public function verifyModeEnum(): ?VerifyMode
    {
        return VerifyMode::tryFrom($this->verifyMode);
    }

    /**
     * Get a human-readable verify mode label.
     */
    public function verifyModeLabel(): string
    {
        return VerifyMode::nameFor($this->verifyMode);
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'verify_mode' => $this->verifyMode,
            'verify_mode_label' => $this->verifyModeLabel(),
            'work_code' => $this->workCode,
            'serial_number' => $this->serialNumber,
        ];
    }
}
