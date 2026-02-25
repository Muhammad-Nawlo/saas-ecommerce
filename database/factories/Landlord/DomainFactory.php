<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Models\Domain;
use App\Landlord\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'domain' => fake()->unique()->domainWord() . '.' . fake()->domainName(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
