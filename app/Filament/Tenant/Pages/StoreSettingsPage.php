<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class StoreSettingsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.tenant.pages.store-settings-page';

    /** @var string|\UnitEnum|null */
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

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
