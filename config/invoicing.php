<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-generate invoice on order paid
    |--------------------------------------------------------------------------
    | When a financial order is marked as paid, optionally create a draft invoice.
    */
    'auto_generate_invoice_on_payment' => env('INVOICE_AUTO_GENERATE_ON_PAYMENT', false),

    /*
    |--------------------------------------------------------------------------
    | PDF storage disk and path
    |--------------------------------------------------------------------------
    */
    'pdf_disk' => env('INVOICE_PDF_DISK', 'local'),
    'pdf_path' => 'invoices',
];
