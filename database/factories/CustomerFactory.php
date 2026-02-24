<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer\Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => (string) Str::uuid(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->optional(0.5)->phoneNumber(),
            'email_verified_at' => now(),
            'is_active' => true,
            'last_login_at' => null,
            'meta' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
