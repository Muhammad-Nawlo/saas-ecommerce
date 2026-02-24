<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MultiLocation\InventoryTransferResource\Pages;

use App\Filament\Tenant\Resources\MultiLocation\InventoryTransferResource;
use App\Services\Inventory\InventoryTransferService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateInventoryTransfer extends CreateRecord
{
    protected static string $resource = InventoryTransferResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $transfer = app(InventoryTransferService::class)->transfer(
            $data['product_id'],
            $data['from_location_id'],
            $data['to_location_id'],
            (int) $data['quantity'],
        );
        Notification::make()->title('Transfer completed')->success()->send();
        return $transfer;
    }

    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
