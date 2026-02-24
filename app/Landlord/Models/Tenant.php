<?php

namespace App\Landlord\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    protected $fillable = [
        'id',
        'name',
        'plan_id',
        'stripe_customer_id',
        'status',
    ];
}
