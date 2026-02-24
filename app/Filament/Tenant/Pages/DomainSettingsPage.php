<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Constants\TenantPermissions;
use Illuminate\Contracts\Support\Htmlable;

class DomainSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static string $view = 'filament.tenant.pages.domain-settings-page';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Domain';
    }

    /** Only users with manage domain permission can access. */
    public static function canAccess(): bool
    {
        return tenant('id') !== null && auth()->user()?->can(TenantPermissions::MANAGE_DOMAIN) === true;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Domain settings';
    }

    public function getPrimaryDomain(): string
    {
        $tenant = tenant();
        if ($tenant === null) {
            return '—';
        }
        $domains = $tenant->domains ?? collect();
        $primary = $domains->where('is_primary', true)->first();
        if ($primary) {
            return $primary->domain ?? '—';
        }
        $first = $domains->first();
        return $first ? ($first->domain ?? '—') : '—';
    }

    public function hasCustomDomainFeature(): bool
    {
        try {
            return (bool) tenant_feature('custom_domain');
        } catch (\Throwable) {
            return false;
        }
    }
}
