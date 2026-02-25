<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Orders\Infrastructure\Persistence\OrderItemModel;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderItemModel>
 */
class OrderItemModelFactory extends Factory
{
    protected $model = OrderItemModel::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->numberBetween(500, 20000);
        return [
            'id' => (string) Str::uuid(),
            'order_id' => OrderModel::factory(),
            'product_id' => (string) Str::uuid(),
            'quantity' => $quantity,
            'unit_price_amount' => $unitPrice,
            'unit_price_currency' => 'USD',
            'total_price_amount' => $unitPrice * $quantity,
            'total_price_currency' => 'USD',
        ];
    }
}
