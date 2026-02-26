<?php

namespace App\Landlord\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'plan_id',
        'stripe_customer_id',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (empty($tenant->slug) && ! empty($tenant->name)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    /**
     * Columns that exist on the tenants table; other attributes go to the "data" JSON column.
     */
    protected static function newFactory(): \Database\Factories\Landlord\TenantFactory
    {
        return \Database\Factories\Landlord\TenantFactory::new();
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'status',
            'plan_id',
            'stripe_customer_id',
            'trial_ends_at',
            'suspended_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }
}
