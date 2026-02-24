<?php

declare(strict_types=1);

namespace App\Landlord\Http\Controllers;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Tenant;
use App\Landlord\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts Stripe Checkout (subscription) for a tenant and plan.
 * Redirects to Stripe Hosted Checkout page.
 */
final class BillingCheckoutController
{
    public function __construct(
        private StripeService $stripe
    ) {
    }

    /**
     * POST /billing/checkout/{plan}
     * Body: tenant_id (required)
     * Returns redirect to Stripe Checkout or 4xx with message.
     */
    public function __invoke(Request $request, string $plan): RedirectResponse|JsonResponse
    {
        $tenantId = $request->input('tenant_id') ?? $request->input('tenant');
        if ($tenantId === null || $tenantId === '') {
            return new JsonResponse(
                ['message' => 'tenant_id is required'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            return new JsonResponse(
                ['message' => 'Tenant not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $planModel = Plan::on(config('tenancy.database.central_connection', config('database.default')))
            ->find($plan);
        if ($planModel === null) {
            return new JsonResponse(
                ['message' => 'Plan not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $stripePriceId = $planModel->stripe_price_id ?? null;
        if ($stripePriceId === null || $stripePriceId === '') {
            return new JsonResponse(
                ['message' => 'Plan has no Stripe price configured'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $checkoutUrl = $this->stripe->createCheckoutSession($tenant, $planModel);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['message' => 'Could not create checkout session', 'error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return redirect()->away($checkoutUrl);
    }
}
