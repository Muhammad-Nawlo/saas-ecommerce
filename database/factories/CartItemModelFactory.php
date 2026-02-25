<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Cart\Infrastructure\Persistence\CartItemModel;
use App\Modules\Cart\Infrastructure\Persistence\CartModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CartItemModel>
 */
class CartItemModelFactory extends Factory
{
    protected $model = CartItemModel::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->numberBetween(500, 50000);
        return [
            'id' => (string) Str::uuid(),
            'cart_id' => CartModel::factory(),
            'product_id' => (string) Str::uuid(),
            'quantity' => $quantity,
            'unit_price_amount' => $unitPrice,
            'unit_price_currency' => 'USD',
            'total_price_amount' => $unitPrice * $quantity,
            'total_price_currency' => 'USD',
        ];
    }
}
