<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Api\Controllers;

use App\Modules\Payments\Application\Commands\CancelPaymentCommand;
use App\Modules\Payments\Application\Commands\ConfirmPaymentCommand;
use App\Modules\Payments\Application\Commands\CreatePaymentCommand;
use App\Modules\Payments\Application\Commands\RefundPaymentCommand;
use App\Modules\Payments\Application\DTOs\PaymentDTO;
use App\Modules\Payments\Application\Handlers\CancelPaymentHandler;
use App\Modules\Payments\Application\Handlers\ConfirmPaymentHandler;
use App\Modules\Payments\Application\Handlers\CreatePaymentHandler;
use App\Modules\Payments\Application\Handlers\RefundPaymentHandler;
use App\Modules\Payments\Domain\Repositories\PaymentRepository;
use App\Modules\Payments\Domain\ValueObjects\OrderId;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Payments\Http\Api\Requests\ConfirmPaymentRequest;
use App\Modules\Payments\Http\Api\Requests\CreatePaymentRequest;
use App\Modules\Payments\Http\Api\Resources\PaymentResource;
use App\Modules\Shared\Domain\Exceptions\BusinessRuleViolation;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class PaymentController
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private CreatePaymentHandler $createPaymentHandler,
        private ConfirmPaymentHandler $confirmPaymentHandler,
        private RefundPaymentHandler $refundPaymentHandler,
        private CancelPaymentHandler $cancelPaymentHandler
    ) {
    }

    public function store(CreatePaymentRequest $request): PaymentResource|JsonResponse
    {
        $tenant = tenant();
        if ($tenant === null) {
            return new JsonResponse(['message' => 'Tenant context required'], Response::HTTP_FORBIDDEN);
        }
        $command = new CreatePaymentCommand(
            tenantId: (string) $tenant->getTenantKey(),
            orderId: $request->validated('order_id'),
            amountMinorUnits: (int) $request->validated('amount_minor_units'),
            currency: $request->validated('currency'),
            provider: $request->validated('provider')
        );
        try {
            [$payment, $clientSecret] = ($this->createPaymentHandler)($command);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return (new PaymentResource(PaymentDTO::fromPayment($payment)))
            ->additional(['client_secret' => $clientSecret])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function confirm(ConfirmPaymentRequest $request, string $paymentId): PaymentResource|JsonResponse
    {
        $command = new ConfirmPaymentCommand(
            paymentId: $paymentId,
            providerPaymentId: $request->validated('provider_payment_id', '')
        );
        try {
            ($this->confirmPaymentHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (BusinessRuleViolation $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $payment = $this->paymentRepository->findById(PaymentId::fromString($paymentId));
        assert($payment !== null);
        return new PaymentResource(PaymentDTO::fromPayment($payment));
    }

    public function refund(string $paymentId): PaymentResource|JsonResponse
    {
        $command = new RefundPaymentCommand(paymentId: $paymentId);
        try {
            ($this->refundPaymentHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (BusinessRuleViolation $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $payment = $this->paymentRepository->findById(PaymentId::fromString($paymentId));
        assert($payment !== null);
        return new PaymentResource(PaymentDTO::fromPayment($payment));
    }

    public function cancel(string $paymentId): PaymentResource|JsonResponse
    {
        $command = new CancelPaymentCommand(paymentId: $paymentId);
        try {
            ($this->cancelPaymentHandler)($command);
        } catch (DomainException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (BusinessRuleViolation $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $payment = $this->paymentRepository->findById(PaymentId::fromString($paymentId));
        assert($payment !== null);
        return new PaymentResource(PaymentDTO::fromPayment($payment));
    }

    public function indexByOrder(string $orderId): AnonymousResourceCollection|JsonResponse
    {
        try {
            $orderIdVo = OrderId::fromString($orderId);
        } catch (InvalidValueObject) {
            return new JsonResponse(['message' => 'Invalid order ID'], Response::HTTP_NOT_FOUND);
        }
        $payments = $this->paymentRepository->findByOrderId($orderIdVo);
        $dtos = array_map(fn ($p) => PaymentDTO::fromPayment($p), $payments);
        return PaymentResource::collection($dtos);
    }
}
