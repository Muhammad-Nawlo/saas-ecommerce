<?php

declare(strict_types=1);

namespace App\Policies;

use App\Constants\TenantPermissions;
use App\Models\Invoice\Invoice;
use App\Models\User;

/**
 * Only owner or manager (with manage invoices permission) can issue or void.
 * Staff: view only.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return tenant('id') !== null && $user->can(TenantPermissions::VIEW_INVOICES);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return tenant('id') !== null
            && $invoice->tenant_id === tenant('id')
            && $user->can(TenantPermissions::VIEW_INVOICES);
    }

    public function create(User $user): bool
    {
        return tenant('id') !== null && $user->can(TenantPermissions::MANAGE_INVOICES);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return tenant('id') !== null
            && $invoice->tenant_id === tenant('id')
            && !$invoice->isLocked()
            && $user->can(TenantPermissions::MANAGE_INVOICES);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return tenant('id') !== null
            && $invoice->tenant_id === tenant('id')
            && $user->can(TenantPermissions::MANAGE_INVOICES);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return tenant('id') !== null
            && $invoice->tenant_id === tenant('id')
            && $user->can(TenantPermissions::MANAGE_INVOICES);
    }
}
