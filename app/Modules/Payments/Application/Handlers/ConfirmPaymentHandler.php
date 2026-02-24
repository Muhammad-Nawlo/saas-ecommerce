<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Handlers;

use App\Modules\Orders\Application\Services\OrderApplicationService;
use App\Modules\Payments\Application\Commands\ConfirmPaymentCommand;
use App\Modules\Payments\Application\Services\PaymentService;
use App\Modules\Payments\Domain\Repositories\PaymentRepository;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class ConfirmPaymentHandler
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private PaymentService $paymentService,
        private OrderApplicationService $orderApplicationService,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(ConfirmPaymentCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $paymentId = PaymentId::fromString($command->paymentId);
            $payment = $this->paymentRepository->findById($paymentId);
            if ($payment === null) {
                throw new DomainException('Payment not found');
            }
            $providerPaymentId = $payment->providerPaymentId() ?? $command->providerPaymentId;
            if ($providerPaymentId === null || $providerPaymentId === '') {
                throw new DomainException('Provider payment id is required to confirm');
            }
            $this->paymentService->confirmPayment($payment);
            if ($payment->status()->value() === 'pending') {
                $payment->authorize($providerPaymentId);
            }
            $payment->markSucceeded();
            $this->orderApplicationService->markOrderAsPaid($payment->orderId()->value());
            $this->paymentRepository->save($payment);
        });
    }
}
