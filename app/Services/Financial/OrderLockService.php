<?php

declare(strict_types=1);

namespace App\Services\Financial;

use App\Events\Financial\OrderLocked;
use App\Models\Financial\FinancialOrder;
use App\Services\Currency\OrderCurrencySnapshotService;
use App\Models\Financial\FinancialOrderItem;
use App\Models\Financial\FinancialOrderTaxLine;
use App\Models\Financial\TaxRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Locks an order: computes totals, snapshots items and tax, sets locked_at.
 * After lock the order is immutable. Call when transitioning draft → pending.
 * Also fills currency snapshot (base/display amounts) when multi-currency is used.
 */
final class OrderLockService
{
    public function __construct(
        private TaxCalculator $taxCalculator,
        private OrderCurrencySnapshotService $currencySnapshotService,
    ) {}

    /**
     * @param array<int, array{id: string, name: string, type: string, discount_cents: int}>|null $appliedPromotions Snapshot from operational order; immutable after lock
     */
    public function lock(FinancialOrder $order, ?string $countryCode = null, ?string $regionCode = null, ?array $appliedPromotions = null): void
    {
        if ($order->isLocked()) {
            throw new InvalidArgumentException('Order is already locked.');
        }
        if ($order->status !== FinancialOrder::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft orders can be locked.');
        }

        $order->load('items');
        if ($order->items->isEmpty()) {
            throw new InvalidArgumentException('Order must have at least one item to lock.');
        }

        $applicableRates = $this->getApplicableRates($order, $countryCode, $regionCode);
        $result = $this->taxCalculator->calculate($order, $applicableRates);

        $discountCents = (int) ($order->discount_total_cents ?? 0);
        DB::transaction(function () use ($order, $result, $applicableRates, $discountCents, $appliedPromotions): void {
            $order->subtotal_cents = $result->subtotal_cents;
            $order->tax_total_cents = $result->tax_total_cents;
            $order->total_cents = $result->subtotal_cents - $discountCents + $result->tax_total_cents;
            $order->locked_at = now();
            $order->status = FinancialOrder::STATUS_PENDING;
                foreach ($order->items as $item) {
                $item->subtotal_cents = $item->quantity * $item->unit_price_cents;
                $item->tax_cents = $this->itemTaxCents($item->subtotal_cents, $applicableRates);
                $item->total_cents = $item->subtotal_cents + $item->tax_cents;
                $item->save();
            }

            FinancialOrderTaxLine::where('order_id', $order->id)->delete();
            foreach ($result->taxLines as $line) {
                FinancialOrderTaxLine::create([
                    'order_id' => $order->id,
                    'tax_rate_name' => $line['name'],
                    'tax_percentage' => $line['percentage'],
                    'taxable_amount_cents' => $line['taxable_amount_cents'],
                    'tax_amount_cents' => $line['tax_amount_cents'],
                ]);
            }

            $order->snapshot = $this->buildSnapshot($order, $result, $appliedPromotions ?? []);
            $this->currencySnapshotService->fillSnapshot($order);
            $order->save();
        });

        event(new OrderLocked($order));
        Log::info('Financial order locked', [
            'tenant_id' => $order->tenant_id,
            'financial_order_id' => $order->id,
            'order_number' => $order->order_number,
            'total_cents' => $order->total_cents,
        ]);
    }

    private function getApplicableRates(FinancialOrder $order, ?string $countryCode, ?string $regionCode): array
    {
        $query = TaxRate::where('is_active', true);
        if ($order->tenant_id !== null) {
            $query->where(function ($q) use ($order): void {
                $q->where('tenant_id', $order->tenant_id)->orWhereNull('tenant_id');
            });
        }
        if ($countryCode !== null) {
            $query->where('country_code', $countryCode);
        }
        if ($regionCode !== null) {
            $query->where(function ($q) use ($regionCode): void {
                $q->where('region_code', $regionCode)->orWhereNull('region_code');
            });
        }
        return $query->get()->all();
    }

    /** @param array<TaxRate> $rates */
    private function itemTaxCents(int $itemSubtotalCents, array $rates): int
    {
        $tax = 0;
        foreach ($rates as $r) {
            $tax += (int) round($itemSubtotalCents * (float) $r->percentage / 100);
        }
        return $tax;
    }

    /**
     * @param array<int, array{id: string, name: string, type: string, discount_cents: int}> $appliedPromotions
     */
    private function buildSnapshot(FinancialOrder $order, TaxResult $result, array $appliedPromotions = []): array
    {
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'subtotal_cents' => $item->quantity * $item->unit_price_cents,
                'tax_cents' => $item->tax_cents ?? 0,
                'total_cents' => ($item->quantity * $item->unit_price_cents) + ($item->tax_cents ?? 0),
                'metadata' => $item->metadata,
            ];
        }
        $discountCents = (int) ($order->discount_total_cents ?? 0);
        return [
            'locked_at' => $order->locked_at?->toIso8601String(),
            'currency' => $order->currency,
            'subtotal_cents' => $result->subtotal_cents,
            'discount_total_cents' => $discountCents,
            'applied_promotions' => $appliedPromotions,
            'tax_total_cents' => $result->tax_total_cents,
            'total_cents' => $result->subtotal_cents - $discountCents + $result->tax_total_cents,
            'tax_lines' => $result->taxLines,
            'items' => $items,
        ];
    }
}
