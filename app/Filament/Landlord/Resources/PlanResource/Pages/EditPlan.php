<?php

declare(strict_types=1);

namespace App\Filament\Landlord\Resources\PlanResource\Pages;

use App\Filament\Landlord\Resources\PlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (): void {
                    $plan = $this->record;
                    $activeCount = $plan->subscriptions()->where('status', 'active')->count();
                    if ($activeCount > 0) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot delete plan')
                            ->body("This plan has {$activeCount} active subscription(s).")
                            ->danger()
                            ->send();
                        throw new \Illuminate\Validation\ValidationException([]);
                    }
                }),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }
}
