<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tenant panel roles. Stored in tenant DB only.
 * Hierarchy: owner > manager > staff > viewer (for privilege escalation checks).
 */
enum TenantRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';
    case Viewer = 'viewer';

    /** Role level for "cannot assign role higher than own" (higher = more privileged). */
    public function level(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Manager => 3,
            self::Staff => 2,
            self::Viewer => 1,
        };
    }

    public static function fromName(string $name): ?self
    {
        return match (strtolower($name)) {
            'owner' => self::Owner,
            'manager' => self::Manager,
            'staff' => self::Staff,
            'viewer' => self::Viewer,
            default => null,
        };
    }
}
