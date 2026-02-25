<?php

declare(strict_types=1);

namespace App\Models\Customer;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Tenant-scoped customer identity. Separate from admin User model.
 * Used for storefront API auth via Sanctum.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $email
 * @property string $password
 * @property string $first_name
 * @property string $last_name
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property array|null $meta
 */
class Customer extends Authenticatable implements MustVerifyEmail, \Illuminate\Contracts\Auth\CanResetPassword
{
    use CanResetPassword;
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'tenant_id',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'email_verified_at',
        'is_active',
        'last_login_at',
        'meta',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'meta' => 'array',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CustomerSession::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(OrderModel::class, 'customer_id', 'id');
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /** Scope to current tenant. */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function canLogin(): bool
    {
        return $this->is_active;
    }

    protected static function newFactory(): \Database\Factories\CustomerFactory
    {
        return \Database\Factories\CustomerFactory::new();
    }
}
