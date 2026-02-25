<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryLocationStock;
use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLocationStock>
 */
class InventoryLocationStockFactory extends Factory
{
    protected $model = InventoryLocationStock::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(10, 500);
        return [
            'product_id' => (string) \Illuminate\Support\Str::uuid(),
            'location_id' => InventoryLocation::factory(),
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 10,
        ];
    }
}
