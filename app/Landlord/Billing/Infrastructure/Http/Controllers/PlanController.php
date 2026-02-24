<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Infrastructure\Http\Controllers;

use App\Landlord\Billing\Application\Commands\ActivatePlanCommand;
use App\Landlord\Billing\Application\Commands\CreatePlanCommand;
use App\Landlord\Billing\Application\Commands\DeactivatePlanCommand;
use App\Landlord\Billing\Application\DTOs\PlanDTO;
use App\Landlord\Billing\Application\Handlers\ActivatePlanHandler;
use App\Landlord\Billing\Application\Handlers\CreatePlanHandler;
use App\Landlord\Billing\Application\Handlers\DeactivatePlanHandler;
use App\Landlord\Billing\Domain\Repositories\PlanRepository;
use App\Landlord\Billing\Infrastructure\Http\Requests\CreatePlanRequest;
use App\Landlord\Billing\Infrastructure\Http\Resources\PlanResource;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class PlanController
{
    public function __construct(
        private PlanRepository $planRepository,
        private CreatePlanHandler $createPlanHandler,
        private ActivatePlanHandler $activatePlanHandler,
        private DeactivatePlanHandler $deactivatePlanHandler
    ) {
    }

    /**
     * @return AnonymousResourceCollection<int, PlanResource>
     */
    public function index(): AnonymousResourceCollection
    {
        $plans = $this->planRepository->findActivePlans();
        $dtos = array_map(fn ($p) => PlanDTO::fromPlan($p), $plans);
        return PlanResource::collection($dtos);
    }

    public function store(CreatePlanRequest $request): PlanResource|JsonResponse
    {
        try {
            $command = new CreatePlanCommand(
                name: $request->validated('name'),
                stripePriceId: $request->validated('stripe_price_id'),
                priceAmount: (int) $request->validated('price_amount'),
                currency: $request->validated('currency'),
                billingInterval: $request->validated('billing_interval')
            );
            $plan = ($this->createPlanHandler)($command);
            return new PlanResource(PlanDTO::fromPlan($plan));
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function activate(string $id): JsonResponse
    {
        try {
            ($this->activatePlanHandler)(new ActivatePlanCommand(planId: $id));
            return new JsonResponse(['message' => 'Plan activated'], Response::HTTP_OK);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function deactivate(string $id): JsonResponse
    {
        try {
            ($this->deactivatePlanHandler)(new DeactivatePlanCommand(planId: $id));
            return new JsonResponse(['message' => 'Plan deactivated'], Response::HTTP_OK);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
