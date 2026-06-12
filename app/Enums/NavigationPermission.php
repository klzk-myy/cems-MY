<?php

namespace App\Enums;

/**
 * Navigation Permission Matrix
 *
 * Defines which roles can access which navigation sections
 * Used for role-based navigation filtering
 */
enum NavigationPermission
{
    // Main - All roles
    case Main;

    // Operations - Manager, Admin
    case Operations;

    // Counter Management - Manager, Admin
    case CounterManagement;

    // Stock Management - Manager, Admin
    case StockManagement;

    // Compliance & AML - ComplianceOfficer, Admin
    case Compliance;

    // Accounting - Manager, Admin
    case Accounting;

    // Reports - Manager, Admin
    case Reports;

    // System - Admin only
    case System;

    /**
     * Check if role has access to section
     */
    public function canAccess(UserRole $role): bool
    {
        return match ($this) {
            self::Main => true,
            self::Operations, self::CounterManagement, self::StockManagement => $role->isManager(),
            self::Compliance => $role->isComplianceOfficer(),
            self::Accounting, self::Reports => $role->isManager(),
            self::System => $role->isAdmin(),
        };
    }

    /**
     * Get all sections accessible by role
     */
    public static function forRole(UserRole $role): array
    {
        $accessible = [];

        foreach (self::cases() as $permission) {
            if ($permission->canAccess($role)) {
                $accessible[] = $permission;
            }
        }

        return $accessible;
    }
}
