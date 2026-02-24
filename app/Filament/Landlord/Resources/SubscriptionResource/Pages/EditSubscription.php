<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources\SubscriptionResource\Pages;

use App\Filament\Landlord\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancelAtPeriodEnd')
                ->label('Cancel at period end')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'active' && !$this->record->cancel_at_period_end)
                ->action(function () {
                    $this->record->update(['cancel_at_period_end' => true]);
                    $this->refreshFormData(['cancel_at_period_end']);
                })
                ->requiresConfirmation(),
        ];
    }
}
