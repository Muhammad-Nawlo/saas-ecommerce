<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources\FeatureResource\Pages;

use App\Filament\Landlord\Resources\FeatureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeatures extends ListRecords
{
    protected static string $resource = FeatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
