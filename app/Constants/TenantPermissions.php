<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Tenant permission names. Use these for consistency and Filament/can() checks.
 * Stored in tenant DB only.
 */
final class TenantPermissions
{
    public const VIEW_PRODUCTS = 'view products';
    public const CREATE_PRODUCTS = 'create products';
    public const EDIT_PRODUCTS = 'edit products';
    public const DELETE_PRODUCTS = 'delete products';

    public const VIEW_ORDERS = 'view orders';
    public const EDIT_ORDERS = 'edit orders';

    public const VIEW_CUSTOMERS = 'view customers';

    public const VIEW_INVENTORY = 'view inventory';
    public const EDIT_INVENTORY = 'edit inventory';

    public const MANAGE_BILLING = 'manage billing';
    public const VIEW_INVOICES = 'view invoices';
    public const MANAGE_INVOICES = 'manage invoices';
    public const MANAGE_DOMAIN = 'manage domain';
    public const MANAGE_ROLES = 'manage roles';
    public const MANAGE_USERS = 'manage users';

    /** All tenant permission names. */
    public static function all(): array
    {
        return [
            self::VIEW_PRODUCTS,
            self::CREATE_PRODUCTS,
            self::EDIT_PRODUCTS,
            self::DELETE_PRODUCTS,
            self::VIEW_ORDERS,
            self::EDIT_ORDERS,
            self::VIEW_CUSTOMERS,
            self::VIEW_INVENTORY,
            self::EDIT_INVENTORY,
            self::MANAGE_BILLING,
            self::VIEW_INVOICES,
            self::MANAGE_INVOICES,
            self::MANAGE_DOMAIN,
            self::MANAGE_ROLES,
            self::MANAGE_USERS,
        ];
    }
}
