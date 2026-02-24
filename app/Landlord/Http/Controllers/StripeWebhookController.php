<?php

declare(strict_types=1);

namespace App\Landlord\Http\Controllers;

use App\Landlord\Billing\Application\Commands\SyncStripeSubscriptionCommand;
use App\Landlord\Billing\Application\Handlers\SyncStripeSubscriptionHandler;
use App\Landlord\Billing\Application\Services\BillingService;
use App\Landlord\Billing\Infrastructure\Persistence\SubscriptionModel;
use App\Landlord\Models\StripeEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles Stripe webhook events for subscription lifecycle.
 * Events: checkout.session.completed, invoice.payment_succeeded, invoice.payment_failed,
 * customer.subscription.updated, customer.subscription.deleted.
 */
final class StripeWebhookController
{
    private const string IDEMPOTENCY_PREFIX = 'stripe_webhook:';
    private const int IDEMPOTENCY_TTL_SECONDS = 86400;
    private const int GRACE_PERIOD_DAYS = 7;

    public function __construct(
        private BillingService $billingService,
        private SyncStripeSubscriptionHandler $syncHandler
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $secret = config('services.stripe.webhook_secret');
        if ($secret === null || $secret === '') {
            Log::warning('Stripe webhook: STRIPE_WEBHOOK_SECRET not configured');
            return new JsonResponse(['error' => 'Webhook not configured'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook: Invalid payload', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook: Invalid signature', ['error' => $e->getMessage()]);
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
            Log::error('Stripe webhook: Failed to process event', [
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
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event->data->object);
                break;
            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object);
                break;
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;
            default:
                break;
        }
    }

    private function handleCheckoutSessionCompleted(\Stripe\Checkout\Session $session): void
    {
        $subscriptionId = $session->subscription;
        $customerId = $session->customer;
        if ($subscriptionId === null || $customerId === null) {
            Log::warning('Stripe webhook: checkout.session.completed missing subscription or customer');
            return;
        }
        $tenantId = $session->metadata['tenant_id'] ?? $session->client_reference_id ?? null;
        if ($tenantId === null || $tenantId === '') {
            Log::warning('Stripe webhook: checkout.session.completed missing tenant_id in metadata');
            return;
        }
        $this->billingService->createSubscriptionFromStripeCheckout(
            (string) $subscriptionId,
            (string) $tenantId,
            (string) $customerId
        );
    }

    private function handleInvoicePaymentSucceeded(\Stripe\Invoice $invoice): void
    {
        $subId = $invoice->subscription;
        if ($subId !== null) {
            ($this->syncHandler)(new SyncStripeSubscriptionCommand(stripeSubscriptionId: (string) $subId));
        }
    }

    private function handleInvoicePaymentFailed(\Stripe\Invoice $invoice): void
    {
        $subId = $invoice->subscription;
        if ($subId === null) {
            return;
        }
        $stripeSubId = (string) $subId;
        ($this->syncHandler)(new SyncStripeSubscriptionCommand(stripeSubscriptionId: $stripeSubId));
        // Mark past_due and set past_due_at for grace period (7 days then suspend).
        $conn = config('tenancy.database.central_connection', config('database.default'));
        SubscriptionModel::on($conn)
            ->where('stripe_subscription_id', $stripeSubId)
            ->update([
                'status' => 'past_due',
                'past_due_at' => now(),
            ]);
    }

    private function handleSubscriptionUpdated(\Stripe\Subscription $sub): void
    {
        ($this->syncHandler)(new SyncStripeSubscriptionCommand(stripeSubscriptionId: (string) $sub->id));
    }

    private function handleSubscriptionDeleted(\Stripe\Subscription $sub): void
    {
        ($this->syncHandler)(new SyncStripeSubscriptionCommand(stripeSubscriptionId: (string) $sub->id));
    }

    private function alreadyProcessed(string $eventId): bool
    {
        if (\Illuminate\Support\Facades\Cache::has(self::IDEMPOTENCY_PREFIX . $eventId)) {
            return true;
        }
        return StripeEvent::where('event_id', $eventId)->exists();
    }

    private function markProcessed(string $eventId): void
    {
        \Illuminate\Support\Facades\Cache::put(self::IDEMPOTENCY_PREFIX . $eventId, true, self::IDEMPOTENCY_TTL_SECONDS);
        StripeEvent::create([
            'event_id' => $eventId,
            'processed_at' => now(),
        ]);
    }
}
