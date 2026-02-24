<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Models\Invoice\Invoice;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Generate PDF from invoice snapshot. Requires barryvdh/laravel-dompdf.
 * PDF is generated from snapshot JSON (immutable); stored under config('invoicing.pdf_path').
 */
final class InvoicePdfGenerator
{
    public function generate(Invoice $invoice): string
    {
        if (!$invoice->isIssued()) {
            throw new InvalidArgumentException('Only issued invoices can have PDFs generated.');
        }
        $snapshot = $invoice->snapshot ?? [];
        $invoice->load('items', 'payments', 'creditNotes', 'customer');
        $path = $this->storagePath($invoice);
        $disk = config('invoicing.pdf_disk', 'local');
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->path($path);
        }
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            throw new InvalidArgumentException('barryvdh/laravel-dompdf is required for PDF generation. Install it with: composer require barryvdh/laravel-dompdf');
        }
        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'snapshot' => $snapshot,
            'items' => $snapshot['items'] ?? $invoice->items->toArray(),
            'tenantName' => tenant()?->name ?? config('app.name'),
        ])->render();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        Storage::disk($disk)->put($path, $pdf->output());
        return Storage::disk($disk)->path($path);
    }

    public function getStoragePath(Invoice $invoice): string
    {
        return $this->storagePath($invoice);
    }

    private function storagePath(Invoice $invoice): string
    {
        $base = config('invoicing.pdf_path', 'invoices');
        return $base . '/' . $invoice->tenant_id . '/' . $invoice->invoice_number . '.pdf';
    }
}
