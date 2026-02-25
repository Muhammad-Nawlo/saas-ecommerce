<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Structured instrumentation for metrics and observability.
 * Integration point for Prometheus, Datadog, NewRelic, or OpenTelemetry.
 * No vendor lock-in: emits structured log events by default; swap driver in config to push to external systems.
 */
final class Instrumentation
{
    public const CHANNEL = 'stack';

    /**
     * Emit a business event for metrics/observability. Logs structured payload; can be wired to metrics backend.
     *
     * @param  string  $event  Event name (e.g. order_created, payment_confirmed).
     * @param  array<string, mixed>  $payload  Must include tenant_id when applicable; entity_type, entity_id, etc.
     */
    public static function event(string $event, array $payload = []): void
    {
        $payload['event'] = $event;
        $payload['timestamp'] = now()->toIso8601String();
        Log::channel(self::CHANNEL)->info('instrumentation:' . $event, $payload);
    }

    public static function orderCreated(string $tenantId, string $orderId, ?string $financialOrderId = null): void
    {
        self::event('order_created', array_filter([
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'financial_order_id' => $financialOrderId,
        ]));
    }

    public static function paymentConfirmed(string $tenantId, string $paymentId, string $orderId): void
    {
        self::event('payment_confirmed', [
            'tenant_id' => $tenantId,
            'payment_id' => $paymentId,
            'order_id' => $orderId,
        ]);
    }

    public static function invoiceIssued(string $tenantId, string $invoiceId, string $orderId): void
    {
        self::event('invoice_issued', [
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'order_id' => $orderId,
        ]);
    }

    public static function refundProcessed(string $tenantId, string $orderId, int $amountCents): void
    {
        self::event('refund_processed', [
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'amount_cents' => $amountCents,
        ]);
    }

    public static function subscriptionChanged(string $tenantId, string $eventType, ?string $subscriptionId = null): void
    {
        self::event('subscription_changed', array_filter([
            'tenant_id' => $tenantId,
            'subscription_event' => $eventType,
            'subscription_id' => $subscriptionId,
        ]));
    }
}
