<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startsAt = now();
        $endsAt = $startsAt->copy()->addMonth();
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'stripe_subscription_id' => 'sub_' . fake()->regexify('[A-Za-z0-9]{24}'),
            'current_period_start' => $startsAt,
            'current_period_end' => $endsAt,
            'cancel_at_period_end' => false,
        ];
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Subscription::STATUS_CANCELED]);
    }
}
