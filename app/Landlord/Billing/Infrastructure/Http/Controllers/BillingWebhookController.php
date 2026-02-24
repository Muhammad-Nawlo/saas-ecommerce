<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Infrastructure\Http\Controllers;

use App\Landlord\Billing\Application\Commands\SyncStripeSubscriptionCommand;
use App\Landlord\Billing\Application\Handlers\SyncStripeSubscriptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class BillingWebhookController
{
    private const string IDEMPOTENCY_PREFIX = 'stripe_billing_webhook:';
    private const int IDEMPOTENCY_TTL_SECONDS = 86400;

    public function __construct(
        private SyncStripeSubscriptionHandler $syncHandler
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $secret = config('services.stripe.webhook_secret');
        if ($secret === null || $secret === '') {
            Log::warning('Billing webhook: STRIPE_WEBHOOK_SECRET not configured');
            return new JsonResponse(['error' => 'Webhook not configured'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Billing webhook: Invalid payload', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Billing webhook: Invalid signature', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_BAD_REQUEST);
        }

        $eventId = $event->id ?? '';
        if ($eventId !== '' && $this->alreadyProcessed($eventId)) {
            return new JsonResponse(['received' => true], Response::HTTP_OK);
        }

        try {
            $this->handleEvent($event);
            if ($eventId !== '') {
                $this->markProcessed($eventId);
            }
        } catch (\Throwable $e) {
            Log::error('Billing webhook: Failed to process event', [
                'event_id' => $eventId,
                'type' => $event->type ?? '',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new JsonResponse(['error' => 'Processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new JsonResponse(['received' => true], Response::HTTP_OK);
    }

    private function handleEvent(\Stripe\Event $event): void
    {
        switch ($event->type) {
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;
            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object);
                break;
            case 'invoice.paid':
                $this->handleInvoicePaid($event->data->object);
                break;
            default:
                break;
        }
    }

    private function handleSubscriptionUpdated(\Stripe\Subscription $sub): void
    {
        ($this->syncHandler)(new SyncStripeSubscriptionCommand(stripeSubscriptionId: (string) $sub->id));
    }

    private function handleSubscriptionDeleted(\Stripe\Subscription $sub): void
    {
        ($this->syncHandler)(new SyncStripeSubscriptionCommand(stripeSubscriptionId: (string) $sub->id));
    }

    private function handleInvoicePaymentFailed(\Stripe\Invoice $invoice): void
    {
        $subId = $invoice->subscription;
        if ($subId !== null) {
            ($this->syncHandler)(new SyncStripeSubscriptionCommand(stripeSubscriptionId: (string) $subId));
        }
    }

    private function handleInvoicePaid(\Stripe\Invoice $invoice): void
    {
        $subId = $invoice->subscription;
        if ($subId !== null) {
            ($this->syncHandler)(new SyncStripeSubscriptionCommand(stripeSubscriptionId: (string) $subId));
        }
    }

    private function alreadyProcessed(string $eventId): bool
    {
        return Cache::has(self::IDEMPOTENCY_PREFIX . $eventId);
    }

    private function markProcessed(string $eventId): void
    {
        Cache::put(self::IDEMPOTENCY_PREFIX . $eventId, true, self::IDEMPOTENCY_TTL_SECONDS);
    }
}
