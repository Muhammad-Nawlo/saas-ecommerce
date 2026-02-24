<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Handlers;

use App\Modules\Orders\Application\Services\OrderApplicationService;
use App\Modules\Payments\Application\Commands\CreatePaymentCommand;
use App\Modules\Payments\Application\Services\PaymentService;
use App\Modules\Payments\Domain\Entities\Payment;
use App\Modules\Payments\Domain\Repositories\PaymentRepository;
use App\Modules\Payments\Domain\ValueObjects\OrderId;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Payments\Domain\ValueObjects\PaymentProvider;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class CreatePaymentHandler
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private PaymentService $paymentService,
        private TransactionManager $transactionManager
    ) {
    }

    /**
     * @return array{0: Payment, 1: string} Payment and client secret (or intent id) for the provider
     */
    public function __invoke(CreatePaymentCommand $command): array
    {
        $payment = null;
        $clientSecret = null;
        $this->transactionManager->run(function () use ($command, &$payment, &$clientSecret): void {
            $id = PaymentId::generate();
            $tenantId = TenantId::fromString($command->tenantId);
            $orderId = OrderId::fromString($command->orderId);
            $amount = Money::fromMinorUnits($command->amountMinorUnits, $command->currency);
            $provider = PaymentProvider::fromString($command->provider);
            $payment = Payment::create($id, $tenantId, $orderId, $amount, $provider);
            $metadata = [
                'tenant_id' => $command->tenantId,
                'order_id' => $command->orderId,
                'payment_id' => $id->value(),
            ];
            $result = $this->paymentService->createPaymentIntent($payment, $metadata);
            $payment->setProviderPaymentId($result['provider_payment_id']);
            $clientSecret = $result['client_secret'];
            $this->paymentRepository->save($payment);
        });
        assert($payment !== null && $clientSecret !== null);
        return [$payment, $clientSecret];
    }
}
