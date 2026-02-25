<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition(): array
    {
        $code = fake()->unique()->slug(2);
        return [
            'code' => $code,
            'description' => fake()->sentence(),
            'type' => Feature::TYPE_BOOLEAN,
        ];
    }

    public function limit(): static
    {
        return $this->state(fn (array $attributes) => ['type' => Feature::TYPE_LIMIT]);
    }
}
