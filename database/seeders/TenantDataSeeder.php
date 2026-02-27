<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency\Currency;
use App\Models\Currency\TenantCurrencySetting;
use App\Models\Customer\Customer;
use App\Models\Financial\FinancialOrder;
use App\Models\Invoice\Invoice;
use App\Models\Financial\TaxRate;
use App\Models\User;
use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use App\Modules\Cart\Infrastructure\Persistence\CartItemModel;
use App\Modules\Cart\Infrastructure\Persistence\CartModel;
use App\Modules\Payments\Domain\Events\PaymentSucceeded;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Orders\Infrastructure\Persistence\OrderItemModel;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Modules\Payments\Infrastructure\Persistence\PaymentModel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryLocationStock;
use App\Models\Promotion\Promotion;
use App\Services\Financial\RefundService;
use App\Services\Invoice\InvoiceService;
use App\Services\Ledger\LedgerService;
use Database\Factories\ProductModelFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds tenant database. Run with tenancy initialized.
 * Uses services for financial flow (sync, lock, invoice, ledger, refund).
 */
final class TenantDataSeeder extends Seeder
{
    private const CURRENCY = 'USD';
    private const NUM_PRODUCTS = 20;
    private const NUM_PROMOTIONS = 5;
    private const NUM_CARTS = 10;
    private const NUM_ORDERS = 10;
    private const NUM_REFUNDS = 2;

    public function run(): void
    {
        $tenantId = (string) tenant()->getTenantKey();

        Config::set('invoicing.auto_generate_invoice_on_payment', true);
        Config::set('permission.cache.store', 'array');

        $this->seedTenantRolesAndUsers($tenantId);
        $this->seedCurrenciesAndTax($tenantId);
        $this->seedLedger($tenantId);
        $this->seedCustomers($tenantId);
        $this->seedProducts($tenantId);
        $this->seedInventory($tenantId);
        $this->seedPromotions($tenantId);
        $this->seedCarts($tenantId);
        $this->seedOrdersPaymentsAndFinancialFlow($tenantId);
        $this->issueDraftInvoices();
        $this->seedRefunds($tenantId);
        $this->verifyFinancialIntegrity($tenantId);
    }

    private function seedTenantRolesAndUsers(string $tenantId): void
    {
        $rolesSeeder = new RolesAndPermissionsSeeder();
        $rolesSeeder->seedTenantRolesAndPermissions();

        $centralConnection = config('tenancy.database.central_connection', config('database.default'));
        $suffix = Str::slug($tenantId);

        $tenantAdmin = User::on($centralConnection)->firstOrCreate(
            ['email' => "tenant-admin-{$suffix}@example.com"],
            [
                'name' => 'Tenant Admin ' . $suffix,
                'password' => Hash::make('password'),
                'is_super_admin' => false,
            ]
        );
        $manager = User::on($centralConnection)->firstOrCreate(
            ['email' => "manager-{$suffix}@example.com"],
            [
                'name' => 'Manager ' . $suffix,
                'password' => Hash::make('password'),
                'is_super_admin' => false,
            ]
        );
        $accountant = User::on($centralConnection)->firstOrCreate(
            ['email' => "accountant-{$suffix}@example.com"],
            [
                'name' => 'Accountant ' . $suffix,
                'password' => Hash::make('password'),
                'is_super_admin' => false,
            ]
        );

        $rolesSeeder->assignTenantRoles([
            'tenant_admin' => $tenantAdmin,
            'manager' => $manager,
            'accountant' => $accountant,
        ]);
    }

    private function seedCurrenciesAndTax(string $tenantId): void
    {
        $currency = Currency::firstOrCreate(
            ['code' => self::CURRENCY],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_active' => true,
            ]
        );

        TenantCurrencySetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'base_currency_id' => $currency->id,
                'allow_multi_currency' => false,
                'rounding_strategy' => TenantCurrencySetting::ROUNDING_HALF_UP,
            ]
        );

        TaxRate::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'country_code' => 'US',
            ],
            [
                'name' => 'VAT',
                'percentage' => 10.00,
                'region_code' => null,
                'is_active' => true,
            ]
        );
    }

    private function seedLedger(string $tenantId): void
    {
        app(LedgerService::class)->getOrCreateLedgerForTenant($tenantId, self::CURRENCY);
    }

    private function seedCustomers(string $tenantId): void
    {
        Customer::factory()
            ->count(3)
            ->forTenant($tenantId)
            ->create();
    }

    private function seedProducts(string $tenantId): void
    {
        $factory = ProductModelFactory::new()->forTenant($tenantId);
        for ($i = 0; $i < self::NUM_PRODUCTS; $i++) {
            $factory->create();
        }
    }

    private function seedInventory(string $tenantId): void
    {
        $location = InventoryLocation::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => 'WH01',
            ],
            [
                'name' => 'Main Warehouse',
                'type' => InventoryLocation::TYPE_WAREHOUSE,
                'is_active' => true,
            ]
        );

        $productIds = ProductModel::where('tenant_id', $tenantId)->pluck('id');
        foreach ($productIds as $productId) {
            InventoryLocationStock::firstOrCreate(
                [
                    'product_id' => $productId,
                    'location_id' => $location->id,
                ],
                [
                    'quantity' => 100,
                    'reserved_quantity' => 0,
                    'low_stock_threshold' => 10,
                ]
            );
        }
    }

    private function seedPromotions(string $tenantId): void
    {
        for ($i = 0; $i < self::NUM_PROMOTIONS; $i++) {
            Promotion::create([
                'tenant_id' => $tenantId,
                'name' => 'Promo ' . ($i + 1),
                'type' => $i % 2 === 0 ? Promotion::TYPE_PERCENTAGE : Promotion::TYPE_FIXED,
                'value_cents' => $i % 2 === 0 ? 0 : 1000,
                'percentage' => $i % 2 === 0 ? 10.0 : null,
                'min_cart_cents' => 5000,
                'buy_quantity' => null,
                'get_quantity' => null,
                'starts_at' => now()->subDays(7),
                'ends_at' => now()->addMonths(2),
                'is_stackable' => false,
                'max_uses_total' => 1000,
                'max_uses_per_customer' => 1,
                'is_active' => true,
            ]);
        }
    }

    private function seedCarts(string $tenantId): void
    {
        $productIds = ProductModel::where('tenant_id', $tenantId)->limit(5)->pluck('id');
        for ($i = 0; $i < self::NUM_CARTS; $i++) {
            $cart = CartModel::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'customer_email' => 'cart' . $i . '@example.com',
                'session_id' => Str::random(40),
                'status' => 'active',
                'total_amount' => 0,
                'currency' => self::CURRENCY,
            ]);
            $total = 0;
            foreach ($productIds->take(2) as $productId) {
                $product = ProductModel::find($productId);
                $qty = 1;
                $unitPrice = $product->price_minor_units;
                $lineTotal = $unitPrice * $qty;
                $total += $lineTotal;
                CartItemModel::create([
                    'id' => (string) Str::uuid(),
                    'cart_id' => $cart->id,
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'unit_price_amount' => $unitPrice,
                    'unit_price_currency' => self::CURRENCY,
                    'total_price_amount' => $lineTotal,
                    'total_price_currency' => self::CURRENCY,
                ]);
            }
            $cart->update(['total_amount' => $total]);
        }
    }

    private function seedOrdersPaymentsAndFinancialFlow(string $tenantId): void
    {
        $customers = Customer::where('tenant_id', $tenantId)->get();
        $productIds = ProductModel::where('tenant_id', $tenantId)->pluck('id');
        if ($productIds->isEmpty() || $customers->isEmpty()) {
            return;
        }

        for ($i = 0; $i < self::NUM_ORDERS; $i++) {
            $customer = $customers->random();
            $orderId = (string) Str::uuid();
            $productIdsForOrder = $productIds->random(min(3, $productIds->count()));
            $subtotal = 0;
            $items = [];
            foreach ($productIdsForOrder as $productId) {
                $product = ProductModel::find($productId);
                $qty = random_int(1, 3);
                $unitPrice = $product->price_minor_units;
                $lineTotal = $unitPrice * $qty;
                $subtotal += $lineTotal;
                $items[] = [
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                ];
            }
            $discountCents = 0;
            $totalAmount = $subtotal - $discountCents;

            $order = OrderModel::create([
                'id' => $orderId,
                'tenant_id' => $tenantId,
                'user_id' => $customer->id,
                'customer_email' => $customer->email,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'currency' => self::CURRENCY,
                'discount_total_cents' => $discountCents,
                'applied_promotions' => [],
                'internal_notes' => null,
            ]);

            foreach ($items as $item) {
                OrderItemModel::create([
                    'id' => (string) Str::uuid(),
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price_amount' => $item['unit_price'],
                    'unit_price_currency' => self::CURRENCY,
                    'total_price_amount' => $item['total'],
                    'total_price_currency' => self::CURRENCY,
                ]);
            }

            $paymentId = (string) Str::uuid();
            PaymentModel::create([
                'id' => $paymentId,
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'amount' => $totalAmount,
                'currency' => self::CURRENCY,
                'status' => PaymentModel::STATUS_SUCCEEDED,
                'provider' => 'stripe',
                'provider_payment_id' => 'pi_seed_' . Str::random(16),
                'payment_currency' => self::CURRENCY,
                'payment_amount' => $totalAmount,
                'exchange_rate_snapshot' => [
                    'base_code' => self::CURRENCY,
                    'target_code' => self::CURRENCY,
                    'rate' => 1.0,
                    'source' => 'seed',
                    'effective_at' => now()->toIso8601String(),
                ],
                'payment_amount_base' => $totalAmount,
                'snapshot_hash' => null,
            ]);

            Event::dispatch(new PaymentSucceeded(
                PaymentId::fromString($paymentId),
                $order->id,
                new \DateTimeImmutable()
            ));

            $order->update(['status' => 'paid']);
        }
    }

    private function issueDraftInvoices(): void
    {
        $invoiceService = app(InvoiceService::class);
        Invoice::where('status', Invoice::STATUS_DRAFT)->each(function (Invoice $invoice) use ($invoiceService): void {
            try {
                $invoiceService->issue($invoice);
            } catch (\Throwable $e) {
                $this->command?->warn('Could not issue invoice ' . $invoice->invoice_number . ': ' . $e->getMessage());
            }
        });
    }

    private function seedRefunds(string $tenantId): void
    {
        $paidOrders = FinancialOrder::where('tenant_id', $tenantId)
            ->where('status', FinancialOrder::STATUS_PAID)
            ->orderBy('created_at')
            ->limit(self::NUM_REFUNDS)
            ->get();

        $refundService = app(RefundService::class);
        foreach ($paidOrders as $financialOrder) {
            try {
                $amount = (int) min(5000, (int) $financialOrder->total_cents / 2);
                if ($amount <= 0) {
                    continue;
                }
                $refundService->refund($financialOrder, $amount, 'ref_seed_' . Str::random(8), 'Seeder refund');
            } catch (\Throwable $e) {
                $this->command?->warn('Refund failed for order ' . $financialOrder->order_number . ': ' . $e->getMessage());
            }
        }
    }

    private function verifyFinancialIntegrity(string $tenantId): void
    {
        $reconciliation = app(\App\Modules\Financial\Application\Services\FinancialReconciliationService::class);
        $reconciliation->verify($tenantId);
    }
}
