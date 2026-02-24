<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Landlord (central) permission names. Stored in central DB only.
 */
final class LandlordPermissions
{
    public const VIEW_PLANS = 'view plans';
    public const MANAGE_PLANS = 'manage plans';
    public const VIEW_TENANTS = 'view tenants';
    public const MANAGE_TENANTS = 'manage tenants';
    public const VIEW_SUBSCRIPTIONS = 'view subscriptions';
    public const MANAGE_SUBSCRIPTIONS = 'manage subscriptions';
}
