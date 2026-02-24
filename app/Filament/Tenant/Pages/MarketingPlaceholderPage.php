<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use Illuminate\Contracts\Support\Htmlable;

class MarketingPlaceholderPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static string $view = 'filament.tenant.pages.marketing-placeholder-page';

    protected static ?string $navigationGroup = 'Marketing';

    public static function getNavigationLabel(): string
    {
        return 'Campaigns';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Marketing';
    }
}
