<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Constants\TenantPermissions;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use App\Landlord\Services\StripeService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class BillingPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'filament.tenant.pages.billing-page';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Billing';
    }

    public static function canAccess(): bool
    {
        return tenant('id') !== null && auth()->user()?->can(TenantPermissions::MANAGE_BILLING) === true;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Billing & subscription';
    }

    public function getSubscription(): ?Subscription
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return null;
        }
        $conn = config('tenancy.database.central_connection', config('database.default'));
        return Subscription::on($conn)
            ->where('tenant_id', $tenantId)
            ->with('plan')
            ->orderByDesc('created_at')
            ->first();
    }

    public function getPlanLimits(): array
    {
        $limits = [];
        try {
            $productsLimit = tenant_limit('products_limit');
            $limits['products_limit'] = $productsLimit === null ? 'Unlimited' : (string) $productsLimit;
        } catch (\Throwable) {
            $limits['products_limit'] = '—';
        }
        return $limits;
    }

    public function getPortalUrl(): ?string
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return null;
        }
        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            return null;
        }
        try {
            $stripe = StripeService::fromConfig();
            $returnUrl = url('/dashboard');
            return $stripe->createPortalSession($tenant, $returnUrl);
        } catch (\Throwable) {
            return null;
        }
    }
}
