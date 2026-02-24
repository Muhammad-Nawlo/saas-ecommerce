<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources\PlanResource\Pages;

use App\Filament\Landlord\Resources\PlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
