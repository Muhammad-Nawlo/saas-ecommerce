<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use Illuminate\Contracts\Support\Htmlable;

class StoreSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.tenant.pages.store-settings-page';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Store';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Store settings';
    }
}
