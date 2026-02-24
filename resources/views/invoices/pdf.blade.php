<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
        .header { margin-bottom: 20px; }
        .totals { margin-top: 20px; text-align: right; }
        .status { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tenantName }}</h1>
        <h2>Invoice {{ $invoice->invoice_number ?? '' }}</h2>
        <p>Status: <span class="status">{{ $invoice->status ?? '' }}</span></p>
        @if($invoice->issued_at ?? null)
            <p>Issued: {{ $invoice->issued_at->format('Y-m-d') }}</p>
        @endif
        @if($invoice->due_date ?? null)
            <p>Due: {{ $invoice->due_date->format('Y-m-d') }}</p>
        @endif
    </div>
    @if($invoice->customer ?? null)
        <div>
            <strong>Bill to:</strong><br>
            {{ $invoice->customer->first_name }} {{ $invoice->customer->last_name }}<br>
            {{ $invoice->customer->email }}
        </div>
    @endif
    <table style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit price</th>
                <th>Subtotal</th>
                <th>Tax</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td>{{ $item['quantity'] ?? 0 }}</td>
                    <td>{{ number_format(($item['unit_price_cents'] ?? 0) / 100, 2) }} {{ $invoice->currency ?? 'USD' }}</td>
                    <td>{{ number_format(($item['subtotal_cents'] ?? 0) / 100, 2) }}</td>
                    <td>{{ number_format(($item['tax_cents'] ?? 0) / 100, 2) }}</td>
                    <td>{{ number_format(($item['total_cents'] ?? 0) / 100, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="totals">
        <p>Subtotal: {{ number_format(($invoice->subtotal_cents ?? 0) / 100, 2) }} {{ $invoice->currency ?? 'USD' }}</p>
        <p>Tax: {{ number_format(($invoice->tax_total_cents ?? 0) / 100, 2) }} {{ $invoice->currency ?? 'USD' }}</p>
        <p>Discount: {{ number_format(($invoice->discount_total_cents ?? 0) / 100, 2) }} {{ $invoice->currency ?? 'USD' }}</p>
        <p><strong>Total: {{ number_format(($invoice->total_cents ?? 0) / 100, 2) }} {{ $invoice->currency ?? 'USD' }}</strong></p>
        <p>Paid: {{ number_format($invoice->totalPaidCents() / 100, 2) }} {{ $invoice->currency ?? 'USD' }}</p>
        <p>Balance: {{ number_format($invoice->balanceDueCents() / 100, 2) }} {{ $invoice->currency ?? 'USD' }}</p>
    </div>
</body>
</html>
