<?php

namespace Athwari\ZktecoAdms\Enums;

/**
 * Verification mode constants for the ADMS protocol.
 *
 * These values represent the verification method used by ZKTeco devices
 * when recording attendance via the ADMS (Push) HTTP protocol. Note that
 * these differ from the binary TCP/IP protocol values documented in the
 * ZK protocol specification.
 *
 * Devices may report different numeric codes depending on firmware version
 * and configured verification rules.
 */
enum VerifyMode: int
{
    case Password = 0;
    case Fingerprint = 1;
    case CardLegacy = 2;
    case PasswordAlt = 3;
    case Card = 4;
    case FingerprintCard = 5;
    case FingerprintPassword = 6;
    case CardPassword = 7;
    case CardFingerprintPassword = 8;
    case Other = 9;
    case Face = 15;
    case Palm = 25;

    /**
     * Get a human-readable label for the verify mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Password => 'Password',
            self::Fingerprint => 'Fingerprint',
            self::CardLegacy => 'Card',
            self::PasswordAlt => 'Password',
            self::Card => 'Card',
            self::FingerprintCard => 'Fingerprint+Card',
            self::FingerprintPassword => 'Fingerprint+Password',
            self::CardPassword => 'Card+Password',
            self::CardFingerprintPassword => 'Card+Fingerprint+Password',
            self::Other => 'Other',
            self::Face => 'Face',
            self::Palm => 'Palm',
        };
    }

    /**
     * Get a human-readable name for any verify mode value, including unknown ones.
     */
    public static function nameFor(int $mode): string
    {
        $instance = self::tryFrom($mode);

        return $instance ? $instance->label() : "Unknown ({$mode})";
    }
}
