<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice\Invoice;
use App\Services\Invoice\InvoicePdfGenerator;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfDownloadController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        $path = app(InvoicePdfGenerator::class)->generate($invoice);
        return response()->download($path, $invoice->invoice_number . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
