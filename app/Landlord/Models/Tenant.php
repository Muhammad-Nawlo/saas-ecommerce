<?php

namespace App\Landlord\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'plan_id',
        'stripe_customer_id',
        'status',
    ];
}
