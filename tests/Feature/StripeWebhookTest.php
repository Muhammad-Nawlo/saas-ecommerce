<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Billing\Domain\Contracts\StripeBillingGateway;
use App\Landlord\Billing\Infrastructure\Persistence\PlanModel;
use App\Landlord\Billing\Infrastructure\Persistence\SubscriptionModel;
use App\Landlord\Models\StripeEvent;
use App\Landlord\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Stripe webhook lifecycle tests. Mocks StripeBillingGateway; uses signed payloads.
 * Note: If your migrations define "plans" twice (e.g. default + central), use one DB connection for tests or consolidate migrations.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->centralConn = config('tenancy.database.central_connection', config('database.default'));
    $this->webhookSecret = 'whsec_test_' . Str::random(24);
    config(['services.stripe.webhook_secret' => $this->webhookSecret]);
});

/**
 * Build Stripe-Signature header for webhook payload (v1).
 */
function stripeWebhookSignature(string $payload, string $secret): string
{
    $t = (string) time();
    $signed = $t . '.' . $payload;
    $sig = hash_hmac('sha256', $signed, $secret);

    return 't=' . $t . ',v1=' . $sig;
}

test('webhook checkout.session.completed creates subscription and updates tenant', function (): void {
    $tenant = Tenant::create([
        'name' => 'Webhook Tenant',
        'data' => [],
    ]);
    $plan = PlanModel::on($this->centralConn)->create([
        'name' => 'Pro',
        'stripe_price_id' => 'price_test_pro',
        'price_amount' => 1999,
        'currency' => 'USD',
        'billing_interval' => 'month',
        'is_active' => true,
    ]);

    $stripeSubId = 'sub_test_' . Str::uuid()->toString();
    $customerId = 'cus_test_' . Str::uuid()->toString();

    $payload = json_encode([
        'id' => 'evt_test_' . Str::uuid()->toString(),
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_123',
                'subscription' => $stripeSubId,
                'customer' => $customerId,
                'metadata' => ['tenant_id' => $tenant->id],
                'client_reference_id' => null,
            ],
        ],
    ]);

    $gateway = $this->mock(StripeBillingGateway::class);
    $gateway->shouldReceive('retrieveSubscription')
        ->once()
        ->with($stripeSubId)
        ->andReturn([
            'id' => $stripeSubId,
            'status' => 'active',
            'current_period_start' => time(),
            'current_period_end' => strtotime('+1 month'),
            'cancel_at_period_end' => false,
            'price_id' => 'price_test_pro',
        ]);

    $response = $this->withBody($payload, 'application/json')
        ->post(route('landlord.billing.webhook'), [], [
            'Stripe-Signature' => stripeWebhookSignature($payload, $this->webhookSecret),
            'Content-Type' => 'application/json',
        ]);

    $response->assertStatus(200);

    $sub = SubscriptionModel::on($this->centralConn)->where('stripe_subscription_id', $stripeSubId)->first();
    expect($sub)->not->toBeNull();
    expect($sub->tenant_id)->toBe($tenant->id);
    expect($sub->plan_id)->toBe($plan->id);
    expect($sub->status)->toBe('active');

    $tenant->refresh();
    expect($tenant->plan_id)->toBe($plan->id);
    expect($tenant->stripe_customer_id)->toBe($customerId);
    expect($tenant->status)->toBe('active');
})->group('stripe', 'webhook');

test('webhook invoice.payment_failed sets subscription past_due and past_due_at', function (): void {
    $tenant = Tenant::create(['name' => 'Past Due Tenant', 'data' => []]);
    $plan = PlanModel::on($this->centralConn)->create([
        'name' => 'Basic',
        'stripe_price_id' => 'price_basic',
        'price_amount' => 999,
        'currency' => 'USD',
        'billing_interval' => 'month',
        'is_active' => true,
    ]);
    $stripeSubId = 'sub_pastdue_' . Str::uuid()->toString();
    SubscriptionModel::on($this->centralConn)->create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'stripe_subscription_id' => $stripeSubId,
        'status' => 'active',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'cancel_at_period_end' => false,
    ]);

    $payload = json_encode([
        'id' => 'evt_payment_failed_' . Str::uuid()->toString(),
        'type' => 'invoice.payment_failed',
        'data' => ['object' => ['subscription' => $stripeSubId]],
    ]);

    $gateway = $this->mock(StripeBillingGateway::class);
    $gateway->shouldReceive('retrieveSubscription')
        ->once()
        ->with($stripeSubId)
        ->andReturn([
            'id' => $stripeSubId,
            'status' => 'past_due',
            'current_period_start' => time(),
            'current_period_end' => strtotime('+1 month'),
            'cancel_at_period_end' => false,
            'price_id' => 'price_basic',
        ]);

    $response = $this->withBody($payload, 'application/json')
        ->post(route('landlord.billing.webhook'), [], [
            'Stripe-Signature' => stripeWebhookSignature($payload, $this->webhookSecret),
            'Content-Type' => 'application/json',
        ]);

    $response->assertStatus(200);

    $sub = SubscriptionModel::on($this->centralConn)->where('stripe_subscription_id', $stripeSubId)->first();
    expect($sub)->not->toBeNull();
    expect($sub->status)->toBe('past_due');
    expect($sub->past_due_at)->not->toBeNull();
})->group('stripe', 'webhook');

test('webhook customer.subscription.deleted syncs to cancelled', function (): void {
    $tenant = Tenant::create(['name' => 'Cancel Tenant', 'data' => []]);
    $plan = PlanModel::on($this->centralConn)->create([
        'name' => 'Basic',
        'stripe_price_id' => 'price_basic',
        'price_amount' => 999,
        'currency' => 'USD',
        'billing_interval' => 'month',
        'is_active' => true,
    ]);
    $stripeSubId = 'sub_cancel_' . Str::uuid()->toString();
    SubscriptionModel::on($this->centralConn)->create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'stripe_subscription_id' => $stripeSubId,
        'status' => 'active',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'cancel_at_period_end' => false,
    ]);

    $payload = json_encode([
        'id' => 'evt_sub_deleted_' . Str::uuid()->toString(),
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => ['id' => $stripeSubId]],
    ]);

    $gateway = $this->mock(StripeBillingGateway::class);
    $gateway->shouldReceive('retrieveSubscription')
        ->once()
        ->with($stripeSubId)
        ->andReturn([
            'id' => $stripeSubId,
            'status' => 'canceled',
            'current_period_start' => time(),
            'current_period_end' => strtotime('+1 month'),
            'cancel_at_period_end' => false,
            'price_id' => 'price_basic',
        ]);

    $response = $this->withBody($payload, 'application/json')
        ->post(route('landlord.billing.webhook'), [], [
            'Stripe-Signature' => stripeWebhookSignature($payload, $this->webhookSecret),
            'Content-Type' => 'application/json',
        ]);

    $response->assertStatus(200);

    $sub = SubscriptionModel::on($this->centralConn)->where('stripe_subscription_id', $stripeSubId)->first();
    expect($sub)->not->toBeNull();
    expect($sub->status)->toBe('cancelled');
})->group('stripe', 'webhook');

test('webhook customer.subscription.updated syncs plan on upgrade', function (): void {
    $tenant = Tenant::create(['name' => 'Upgrade Tenant', 'data' => []]);
    $planBasic = PlanModel::on($this->centralConn)->create([
        'name' => 'Basic',
        'stripe_price_id' => 'price_basic',
        'price_amount' => 999,
        'currency' => 'USD',
        'billing_interval' => 'month',
        'is_active' => true,
    ]);
    $planPro = PlanModel::on($this->centralConn)->create([
        'name' => 'Pro',
        'stripe_price_id' => 'price_pro',
        'price_amount' => 1999,
        'currency' => 'USD',
        'billing_interval' => 'month',
        'is_active' => true,
    ]);
    $stripeSubId = 'sub_upgrade_' . Str::uuid()->toString();
    SubscriptionModel::on($this->centralConn)->create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'plan_id' => $planBasic->id,
        'stripe_subscription_id' => $stripeSubId,
        'status' => 'active',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'cancel_at_period_end' => false,
    ]);

    $payload = json_encode([
        'id' => 'evt_sub_updated_' . Str::uuid()->toString(),
        'type' => 'customer.subscription.updated',
        'data' => ['object' => ['id' => $stripeSubId]],
    ]);

    $gateway = $this->mock(StripeBillingGateway::class);
    $gateway->shouldReceive('retrieveSubscription')
        ->once()
        ->with($stripeSubId)
        ->andReturn([
            'id' => $stripeSubId,
            'status' => 'active',
            'current_period_start' => time(),
            'current_period_end' => strtotime('+1 month'),
            'cancel_at_period_end' => false,
            'price_id' => 'price_pro',
        ]);

    $response = $this->withBody($payload, 'application/json')
        ->post(route('landlord.billing.webhook'), [], [
            'Stripe-Signature' => stripeWebhookSignature($payload, $this->webhookSecret),
            'Content-Type' => 'application/json',
        ]);

    $response->assertStatus(200);

    $sub = SubscriptionModel::on($this->centralConn)->where('stripe_subscription_id', $stripeSubId)->first();
    expect($sub)->not->toBeNull();
    expect($sub->plan_id)->toBe($planPro->id);
})->group('stripe', 'webhook');

test('webhook idempotency: duplicate event returns 200 and does not process twice', function (): void {
    $tenant = Tenant::create(['name' => 'Idem Tenant', 'data' => []]);
    $plan = PlanModel::on($this->centralConn)->create([
        'name' => 'Basic',
        'stripe_price_id' => 'price_idem',
        'price_amount' => 999,
        'currency' => 'USD',
        'billing_interval' => 'month',
        'is_active' => true,
    ]);
    $eventId = 'evt_idem_' . Str::uuid()->toString();
    $stripeSubId = 'sub_idem_' . Str::uuid()->toString();
    $customerId = 'cus_idem_' . Str::uuid()->toString();

    $payload = json_encode([
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_idem',
                'subscription' => $stripeSubId,
                'customer' => $customerId,
                'metadata' => ['tenant_id' => $tenant->id],
            ],
        ],
    ]);

    $gateway = $this->mock(StripeBillingGateway::class);
    $gateway->shouldReceive('retrieveSubscription')
        ->once()
        ->with($stripeSubId)
        ->andReturn([
            'id' => $stripeSubId,
            'status' => 'active',
            'current_period_start' => time(),
            'current_period_end' => strtotime('+1 month'),
            'cancel_at_period_end' => false,
            'price_id' => 'price_idem',
        ]);

    $sig = stripeWebhookSignature($payload, $this->webhookSecret);
    $this->withBody($payload, 'application/json')
        ->post(route('landlord.billing.webhook'), [], [
            'Stripe-Signature' => $sig,
            'Content-Type' => 'application/json',
        ])
        ->assertStatus(200);

    $count1 = SubscriptionModel::on($this->centralConn)->where('stripe_subscription_id', $stripeSubId)->count();
    expect($count1)->toBe(1);

    StripeEvent::create(['event_id' => $eventId, 'processed_at' => now()]);

    $gateway->shouldReceive('retrieveSubscription')->never();
    $this->withBody($payload, 'application/json')
        ->post(route('landlord.billing.webhook'), [], [
            'Stripe-Signature' => $sig,
            'Content-Type' => 'application/json',
        ])
        ->assertStatus(200);

    $count2 = SubscriptionModel::on($this->centralConn)->where('stripe_subscription_id', $stripeSubId)->count();
    expect($count2)->toBe(1);
})->group('stripe', 'webhook');
