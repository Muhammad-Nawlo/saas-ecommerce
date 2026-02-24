<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\InvoiceResource\Pages;

use App\Filament\Tenant\Resources\InvoiceResource;
use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoiceService;
use App\ValueObjects\Money;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];
        $invoice = $this->record;
        if (!$invoice instanceof Invoice) {
            return $actions;
        }
        if ($invoice->isDraft() && auth()->user()?->can('manage invoices')) {
            $actions[] = Action::make('issue')
                ->label('Issue')
                ->action(function (): void {
                    app(InvoiceService::class)->issue($this->record);
                    Notification::make()->title('Invoice issued')->success()->send();
                    $this->refreshFormData(['status', 'issued_at', 'locked_at']);
                })
                ->requiresConfirmation();
        }
        if ($invoice->isIssued() && $invoice->balanceDueCents() > 0 && auth()->user()?->can('manage invoices')) {
            $actions[] = Action::make('markPaid')
                ->label('Mark as paid')
                ->form([
                    Forms\Components\TextInput::make('amount_cents')
                        ->numeric()
                        ->required()
                        ->default(fn () => $this->record->balanceDueCents())
                        ->label('Amount (cents)'),
                ])
                ->action(function (array $data): void {
                    $amount = Money::fromCents((int) $data['amount_cents'], $this->record->currency);
                    app(InvoiceService::class)->applyPayment($this->record, $amount);
                    Notification::make()->title('Payment applied')->success()->send();
                    $this->refreshFormData(['status', 'paid_at']);
                });
            $actions[] = Action::make('creditNote')
                ->label('Create credit note')
                ->form([
                    Forms\Components\TextInput::make('amount_cents')->numeric()->required()->label('Amount (cents)'),
                    Forms\Components\Textarea::make('reason')->required()->label('Reason'),
                ])
                ->action(function (array $data): void {
                    $amount = Money::fromCents((int) $data['amount_cents'], $this->record->currency);
                    app(InvoiceService::class)->createCreditNote($this->record, $amount, $data['reason']);
                    Notification::make()->title('Credit note created')->success()->send();
                    $this->refreshFormData(['status']);
                });
        }
        if ($invoice->isIssued()) {
            $actions[] = Action::make('downloadPdf')
                ->label('Download PDF')
                ->url(fn () => route('tenant.invoice.pdf', ['invoice' => $invoice->id]))
                ->openUrlInNewTab();
        }
        if ($invoice->isIssued() && auth()->user()?->can('void', $invoice)) {
            $actions[] = Action::make('void')
                ->label('Void')
                ->color('danger')
                ->action(function (): void {
                    app(InvoiceService::class)->void($this->record);
                    Notification::make()->title('Invoice voided')->success()->send();
                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation();
        }
        return $actions;
    }
}
