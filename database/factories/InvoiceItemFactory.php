<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->numberBetween(500, 20000);
        $subtotal = $unitPrice * $quantity;
        $taxCents = (int) round($subtotal * 0.1);
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
            'subtotal_cents' => $subtotal,
            'tax_cents' => $taxCents,
            'total_cents' => $subtotal + $taxCents,
            'metadata' => null,
        ];
    }
}
