<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources\FeatureResource\Pages;

use App\Filament\Landlord\Resources\FeatureResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeature extends CreateRecord
{
    protected static string $resource = FeatureResource::class;
}
