<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(2000, 80000);
        $taxTotal = (int) round($subtotal * 0.1);
        $total = $subtotal + $taxTotal;
        return [
            'tenant_id' => (string) Str::uuid(),
            'order_id' => null,
            'user_id' => null,
            'invoice_number' => 'INV-' . date('Y') . '-' . str_pad((string) fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'USD',
            'base_currency' => null,
            'exchange_rate_snapshot' => null,
            'total_base_cents' => null,
            'subtotal_cents' => $subtotal,
            'tax_total_cents' => $taxTotal,
            'discount_total_cents' => 0,
            'total_cents' => $total,
            'due_date' => now()->addDays(30),
            'issued_at' => null,
            'paid_at' => null,
            'snapshot' => null,
            'snapshot_hash' => null,
            'locked_at' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
            'locked_at' => now(),
        ]);
    }
}
