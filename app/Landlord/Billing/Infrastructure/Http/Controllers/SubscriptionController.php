<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Infrastructure\Http\Controllers;

use App\Landlord\Billing\Application\Commands\CancelSubscriptionCommand;
use App\Landlord\Billing\Application\Commands\SubscribeTenantCommand;
use App\Landlord\Billing\Application\DTOs\SubscriptionDTO;
use App\Landlord\Billing\Application\Handlers\CancelSubscriptionHandler;
use App\Landlord\Billing\Application\Handlers\SubscribeTenantHandler;
use App\Landlord\Billing\Domain\Repositories\SubscriptionRepository;
use App\Landlord\Billing\Infrastructure\Http\Requests\CancelSubscriptionRequest;
use App\Landlord\Billing\Infrastructure\Http\Requests\SubscribeTenantRequest;
use App\Landlord\Billing\Infrastructure\Http\Resources\SubscriptionResource;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class SubscriptionController
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private SubscribeTenantHandler $subscribeTenantHandler,
        private CancelSubscriptionHandler $cancelSubscriptionHandler
    ) {
    }

    public function subscribe(SubscribeTenantRequest $request): SubscriptionResource|JsonResponse
    {
        try {
            $command = new SubscribeTenantCommand(
                tenantId: $request->validated('tenant_id'),
                planId: $request->validated('plan_id'),
                customerEmail: $request->validated('customer_email')
            );
            $subscription = ($this->subscribeTenantHandler)($command);
            return new SubscriptionResource(SubscriptionDTO::fromSubscription($subscription));
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function cancel(CancelSubscriptionRequest $request): JsonResponse
    {
        try {
            ($this->cancelSubscriptionHandler)(new CancelSubscriptionCommand(
                tenantId: $request->validated('tenant_id')
            ));
            return new JsonResponse(['message' => 'Subscription will cancel at period end'], Response::HTTP_OK);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function show(string $tenantId): SubscriptionResource|JsonResponse
    {
        $subscription = $this->subscriptionRepository->findByTenantId($tenantId);
        if ($subscription === null) {
            return new JsonResponse(['message' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }
        Gate::authorize('view', $subscription);
        return new SubscriptionResource(SubscriptionDTO::fromSubscription($subscription));
    }
}
