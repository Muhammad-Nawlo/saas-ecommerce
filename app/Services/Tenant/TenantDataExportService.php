<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Financial\FinancialOrder;
use App\Models\Invoice\Invoice;
use App\Models\Ledger\LedgerTransaction;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Modules\Payments\Infrastructure\Persistence\PaymentModel;
use Illuminate\Support\Carbon;

/**
 * GDPR/enterprise-style structured export of tenant financial and order data.
 * Service-level only; no UI. Returns JSON-serializable array bundle.
 */
final class TenantDataExportService
{
    /**
     * Export orders, financial orders, invoices, payments, ledger transactions for a tenant.
     * Run within tenant context (e.g. tenancy()->initialize($tenant)).
     *
     * @return array{tenant_id: string, exported_at: string, orders: array, financial_orders: array, invoices: array, payments: array, ledger_transactions: array}
     */
    public function export(string $tenantId): array
    {
        $exportedAt = Carbon::now()->toIso8601String();

        $orders = OrderModel::with('items')->orderBy('created_at')->get()->map(fn ($o) => $this->orderToExport($o))->values()->all();
        $financialOrders = FinancialOrder::with('items')->orderBy('locked_at')->orderBy('created_at')->get()->map(fn ($o) => $this->financialOrderToExport($o))->values()->all();
        $invoices = Invoice::with('items')->orderBy('issued_at')->orderBy('created_at')->get()->map(fn ($i) => $this->invoiceToExport($i))->values()->all();
        $payments = PaymentModel::orderBy('created_at')->get()->map(fn ($p) => $this->paymentToExport($p))->values()->all();
        $ledgerTransactions = LedgerTransaction::with('entries')->orderBy('transaction_at')->orderBy('created_at')->get()->map(fn ($t) => $this->ledgerTransactionToExport($t))->values()->all();

        return [
            'tenant_id' => $tenantId,
            'exported_at' => $exportedAt,
            'orders' => $orders,
            'financial_orders' => $financialOrders,
            'invoices' => $invoices,
            'payments' => $payments,
            'ledger_transactions' => $ledgerTransactions,
        ];
    }

    /** @return array<string, mixed> */
    private function orderToExport(OrderModel $o): array
    {
        $arr = $o->toArray();
        $arr['items'] = $o->relationLoaded('items') ? $o->items->map(fn ($i) => $i->toArray())->all() : [];
        return $arr;
    }

    /** @return array<string, mixed> */
    private function financialOrderToExport(FinancialOrder $o): array
    {
        $arr = $o->toArray();
        $arr['items'] = $o->relationLoaded('items') ? $o->items->map(fn ($i) => $i->toArray())->all() : [];
        return $arr;
    }

    /** @return array<string, mixed> */
    private function invoiceToExport(Invoice $i): array
    {
        $arr = $i->toArray();
        $arr['items'] = $i->relationLoaded('items') ? $i->items->map(fn ($item) => $item->toArray())->all() : [];
        return $arr;
    }

    /** @return array<string, mixed> */
    private function paymentToExport(PaymentModel $p): array
    {
        return $p->toArray();
    }

    /** @return array<string, mixed> */
    private function ledgerTransactionToExport(LedgerTransaction $t): array
    {
        $arr = $t->toArray();
        $arr['entries'] = $t->relationLoaded('entries') ? $t->entries->map(fn ($e) => $e->toArray())->all() : [];
        return $arr;
    }
}
