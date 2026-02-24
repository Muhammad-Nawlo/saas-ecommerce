<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Landlord panel roles. Stored in central DB only.
 */
enum LandlordRole: string
{
    case SuperAdmin = 'super_admin';
    case SupportAgent = 'support_agent';
    case FinanceAdmin = 'finance_admin';
}
